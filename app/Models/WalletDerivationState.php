<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key_version', 'next_deposit_index'])]
class WalletDerivationState extends Model
{
    protected function casts(): array
    {
        return ['next_deposit_index' => 'integer'];
    }
}
