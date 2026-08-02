<?php

namespace App\Http\Controllers;

use App\Actions\Budgets\BuildBudgetProgress;
use App\Actions\Budgets\SaveBudget;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Http\Resources\CategoryResource;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Support\FrontendLocalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(
        private readonly SaveBudget $saveBudget,
        private readonly BuildBudgetProgress $buildProgress,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $vaultArmed = $user->vaultIsArmed();

        $budget = $user->budgets()
            ->active()
            ->with('lines.category')
            ->latest('id')
            ->first();

        $categories = Category::query()
            ->availableFor($user)
            ->where('type', TransactionType::Cost)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => (new CategoryResource($category))->resolve($request));

        return Inertia::render('Budgets', [
            // The plan itself, for the editor. Amounts are ciphertext under the
            // vault and the form decrypts them in place.
            'budget' => $budget === null ? null : [
                'id' => $budget->id,
                'title' => $budget->title,
                'income_basis' => $budget->income_basis->value,
                'expected_income' => $budget->expected_income,
                'currency' => $budget->currency->value,
                'is_active' => $budget->is_active,
                'lines' => $budget->lines->map(fn (BudgetLine $line): array => [
                    'id' => $line->id,
                    'category_id' => $line->category_id,
                    'rule_type' => $line->rule_type->value,
                    'percent' => $line->percent === null ? null : (float) $line->percent,
                    'fixed_amount' => $line->fixed_amount,
                    'currency' => $line->currencyWithin($budget)->value,
                    'rollover_enabled' => $line->rollover_enabled,
                ])->values(),
            ],
            // Resolved server-side when the amounts are readable; null under the
            // vault, where `vaultBudget` carries the raw rows instead. Never both.
            'progress' => $budget === null || $vaultArmed
                ? null
                : $this->buildProgress->handle($user, $budget),
            'vaultBudget' => $budget === null || ! $vaultArmed
                ? null
                : $this->buildProgress->clientPayload($user, $budget),
            'categories' => $categories,
            'currencies' => collect(Currency::cases())->map(fn (Currency $currency) => [
                'label' => $currency->label(),
                'value' => $currency->value,
            ]),
            'userCalendar' => FrontendLocalization::normalizeCalendar($user->calendar),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $vaultArmed = $user->vaultIsArmed();
        $validated = $request->validate(SaveBudget::rules($vaultArmed));

        $this->saveBudget->handle(
            $user,
            SaveBudget::normalize($user, $validated, $vaultArmed),
        );

        return back()->with('status', __('budgets.saved'));
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        $this->authoriseOwnership($request, $budget);

        $user = $request->user();
        $vaultArmed = $user->vaultIsArmed();
        $validated = $request->validate(SaveBudget::rules($vaultArmed));

        $this->saveBudget->handle(
            $user,
            SaveBudget::normalize($user, $validated, $vaultArmed, creating: false),
            $budget,
        );

        return back()->with('status', __('budgets.saved'));
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        $this->authoriseOwnership($request, $budget);

        $budget->delete();

        return back()->with('status', __('budgets.deleted'));
    }

    /**
     * 404 rather than 403 — a budget belonging to someone else should not be
     * confirmed to exist.
     */
    private function authoriseOwnership(Request $request, Budget $budget): void
    {
        abort_unless((int) $budget->user_id === (int) $request->user()->id, 404);
    }
}
