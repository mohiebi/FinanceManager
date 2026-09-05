<?php

namespace App\Http\Controllers;

use App\Actions\Miles\PurchaseCosmetic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MilesCosmeticController extends Controller
{
    public function store(Request $request, string $cosmetic, PurchaseCosmetic $purchase): RedirectResponse
    {
        $purchase($request->user(), $cosmetic);

        return back()->with('success', __('Cosmetic equipped.'));
    }
}
