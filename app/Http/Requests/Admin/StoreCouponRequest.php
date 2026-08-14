<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponKind;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Whether this user may administer anything is EnsureUserIsAdmin's job,
        // the same division every other request in this app keeps to.
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Coupon::normalizeCode((string) $this->input('code')),
            'user_email' => mb_strtolower(trim((string) $this->input('user_email'))),
            'note' => trim((string) $this->input('note')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Letters, digits and dashes only: a code is typed by hand and read
            // aloud, so spaces and punctuation are a support burden.
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('coupons', 'code')],
            'kind' => ['required', Rule::enum(CouponKind::class)],

            // Exactly one of these is required, decided by kind in after().
            'percent_off' => ['nullable', 'integer', 'min:1', 'max:100'],
            'amount_off_usd' => ['nullable', 'numeric', 'gt:0', 'max:100000'],

            // Blank means anybody may redeem it.
            'user_email' => ['nullable', 'string', 'email', 'max:255'],

            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_per_user' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->requireAmountForKind($validator);
                $this->resolveTargetUser($validator);
            },
        ];
    }

    /**
     * The attributes to persist, with the target email already resolved.
     *
     * @return array<string, mixed>
     */
    public function couponData(): array
    {
        $kind = CouponKind::from($this->validated('kind'));

        return [
            'code' => $this->validated('code'),
            'kind' => $kind,
            // Only the arm this kind uses is stored, so a coupon can never carry
            // two contradictory amounts.
            'percent_off' => $kind === CouponKind::Percent ? (int) $this->validated('percent_off') : null,
            'amount_off_usd' => $kind === CouponKind::Fixed
                ? number_format((float) $this->validated('amount_off_usd'), 2, '.', '')
                : null,
            'user_id' => $this->targetUserId(),
            'max_redemptions' => $this->validated('max_redemptions'),
            'max_per_user' => $this->validated('max_per_user'),
            'valid_until' => $this->validated('valid_until'),
            'note' => $this->validated('note') ?: null,
            'created_by_admin_id' => $this->user()->getKey(),
        ];
    }

    private function requireAmountForKind(Validator $validator): void
    {
        $kind = CouponKind::tryFrom((string) $this->input('kind'));

        // Branching on the discriminator rather than required_if, matching how
        // StoreInvestmentAssetRequest validates its price-source config.
        match ($kind) {
            CouponKind::Percent => blank($this->input('percent_off'))
                ? $validator->errors()->add('percent_off', __('billing.admin.coupons.percent_required'))
                : null,
            CouponKind::Fixed => blank($this->input('amount_off_usd'))
                ? $validator->errors()->add('amount_off_usd', __('billing.admin.coupons.amount_required'))
                : null,
            default => null,
        };
    }

    private function resolveTargetUser(Validator $validator): void
    {
        $email = (string) $this->input('user_email');

        if ($email === '') {
            return;
        }

        if ($this->targetUserId() === null) {
            $validator->errors()->add('user_email', __('billing.admin.coupons.user_not_found'));
        }
    }

    private function targetUserId(): ?int
    {
        $email = (string) $this->input('user_email');

        if ($email === '') {
            return null;
        }

        return User::query()->whereRaw('LOWER(email) = ?', [$email])->value('id');
    }
}
