<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'date',
    'total_customers',
    'new_customers',
    'active_customers_7d',
    'active_customers_30d',
    'telegram_customers',
    'verified_customers',
    'completed_profiles',
])]
class DailyStat extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'total_customers' => 'integer',
            'new_customers' => 'integer',
            'active_customers_7d' => 'integer',
            'active_customers_30d' => 'integer',
            'telegram_customers' => 'integer',
            'verified_customers' => 'integer',
            'completed_profiles' => 'integer',
        ];
    }
}
