<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        return response()->json([
            'user' => $request->user()->refresh(),
            'requires_profile_completion' => $request->user()->requiresProfileCompletion(),
        ]);
    }
}
