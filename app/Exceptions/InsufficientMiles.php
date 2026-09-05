<?php

namespace App\Exceptions;

use App\Models\MileWallet;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientMiles extends RuntimeException
{
    public function __construct(
        public readonly int $available,
        public readonly int $cost,
        public readonly string $action,
    ) {
        parent::__construct('Insufficient Miles.');
    }

    public static function forWallet(MileWallet $wallet, int $cost, string $action): self
    {
        return new self((int) $wallet->balance, $cost, $action);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'insufficient_miles',
            'available' => $this->available,
            'cost' => $this->cost,
            'shortfall' => max(0, $this->cost - $this->available),
            'action' => $this->action,
        ], 402);
    }
}
