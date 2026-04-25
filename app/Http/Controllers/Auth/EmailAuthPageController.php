<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailAuthPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('auth/EmailAuth', [
            'status' => $request->session()->get('status'),
        ]);
    }
}
