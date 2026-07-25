<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\FeatureTier;
use App\Enums\TransactionType;
use App\Observers\UserEncryptionKeyObserver;
use App\Support\Encryption\UserKeyRing;
use App\Support\FeatureSet;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[ObservedBy([UserEncryptionKeyObserver::class])]
#[Fillable(['name', 'email', 'birthdate', 'locale', 'calendar', 'default_currency', 'password', 'email_verified_at', 'last_active_at', 'signup_source', 'telegram_chat_id', 'telegram_connect_token'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'telegram_chat_id', 'telegram_connect_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Resolved once per instance. The auth guard memoizes the User, so middleware,
     * shared Inertia props, the nav and every dashboard check reuse a single query.
     */
    private ?FeatureSet $featureSet = null;

    /**
     * @return HasMany<Transaction, User>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Transaction, User>
     */
    public function costs(): HasMany
    {
        return $this->transactions()->where('type', TransactionType::Cost->value);
    }

    /**
     * @return HasMany<Transaction, User>
     */
    public function incomes(): HasMany
    {
        return $this->transactions()->where('type', TransactionType::Income->value);
    }

    /**
     * @return HasMany<Category, User>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Investment, User>
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * @return HasMany<InvestmentAsset, User>
     */
    public function investmentAssets(): HasMany
    {
        return $this->hasMany(InvestmentAsset::class);
    }

    /**
     * @return HasMany<SocialAccount, User>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return HasMany<Bill, User>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * @return HasMany<UserFeature, User>
     */
    public function features(): HasMany
    {
        return $this->hasMany(UserFeature::class);
    }

    /**
     * @return HasOne<UserEncryptionKey, User>
     */
    public function encryptionKey(): HasOne
    {
        return $this->hasOne(UserEncryptionKey::class);
    }

    /**
     * Get this user's key row, creating it if it is somehow missing.
     *
     * Normally the observer has already made one; this keeps records created
     * outside the observer (raw inserts, older seeders) from exploding on first
     * encrypted write.
     */
    public function ensureEncryptionKey(): UserEncryptionKey
    {
        $existing = $this->encryptionKey()->first();

        if ($existing instanceof UserEncryptionKey) {
            return $existing;
        }

        $dek = random_bytes(32);

        $key = $this->encryptionKey()->create([
            'version' => 1,
            'wrapped_dek_server' => Crypt::encryptString(base64_encode($dek)),
            'dek_fingerprint' => hash('sha256', $dek),
        ]);

        if (function_exists('sodium_memzero')) {
            sodium_memzero($dek);
        }

        $this->unsetRelation('encryptionKey');

        return $key;
    }

    /**
     * Whether this user has switched on the zero-knowledge vault, leaving the
     * server unable to read their encrypted columns.
     */
    public function vaultIsArmed(): bool
    {
        return app(UserKeyRing::class)->for($this->getKey()) === null;
    }

    /**
     * The user's resolved feature state, merging their sparse overrides over the
     * enum defaults.
     */
    public function featureSet(): FeatureSet
    {
        return $this->featureSet ??= FeatureSet::fromOverrides(
            $this->relationLoaded('features') ? $this->features : $this->features()->get()
        );
    }

    /**
     * Plan entitlement — whether the user may use this feature at all.
     *
     * Distinct from {@see self::hasFeature()}, which also asks whether they turned
     * it on. Every feature is free today; a paid tier plugs in here.
     */
    public function mayUse(Feature $feature): bool
    {
        return $feature->tier() === FeatureTier::Free || $this->isPro();
    }

    public function hasFeature(Feature $feature): bool
    {
        return $this->mayUse($feature) && $this->featureSet()->enabled($feature);
    }

    public function isPro(): bool
    {
        return false;
    }

    public function forgetFeatureSet(): void
    {
        $this->featureSet = null;
        $this->unsetRelation('features');
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function requiresProfileCompletion(): bool
    {
        return blank($this->birthdate);
    }

    public function hasTelegram(): bool
    {
        return filled($this->telegram_chat_id);
    }

    public function isAdmin(): bool
    {
        $adminEmail = trim((string) config('app.admin_email'));

        return $adminEmail !== '' && strcasecmp($this->email, $adminEmail) === 0;
    }

    /**
     * Exclude the configured administrator from customer analytics.
     *
     * @param  Builder<User>  $query
     */
    public function scopeCustomers(Builder $query): void
    {
        $adminEmail = mb_strtolower(trim((string) config('app.admin_email')));

        if ($adminEmail !== '') {
            $query->whereRaw('LOWER(email) != ?', [$adminEmail]);
        }
    }

    /**
     * Restrict to users who have the given feature enabled.
     *
     * Encodes the sparse-override semantics in one place for the query contexts
     * (jobs, CLI) that have no in-memory FeatureSet to consult.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function whereFeatureEnabled(Builder $query, Feature $feature): void
    {
        if ($feature->isCore()) {
            // On for everyone, and FeatureSet ignores overrides for core features —
            // so a stray row must not exclude anyone here either.
            return;
        }

        $feature->enabledByDefault()
            ? $query->whereDoesntHave('features', fn (Builder $query) => $query
                ->where('feature', $feature->value)
                ->where('enabled', false))
            : $query->whereHas('features', fn (Builder $query) => $query
                ->where('feature', $feature->value)
                ->where('enabled', true));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date:Y-m-d',
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
