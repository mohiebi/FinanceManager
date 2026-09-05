<?php

namespace App\Http\Controllers;

use App\Actions\Miles\GiftMiles;
use App\Http\Requests\GiftMilesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class MilesGiftController extends Controller
{
    public function __invoke(GiftMilesRequest $request, GiftMiles $giftMiles): RedirectResponse
    {
        $recipient = User::query()->findOrFail($request->integer('recipient_id'));
        $giftMiles($request->user(), $recipient, $request->integer('amount'));

        return back()->with('success', __('Miles sent.'));
    }
}
