<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'birthdate', 'locale', 'calendar', 'default_currency', 'password', 'email_verified_at', 'last_active_at', 'signup_source', 'telegram_chat_id', 'telegram_connect_token'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'telegram_chat_id', 'telegram_connect_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

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
