<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DevQuickLoginController extends Controller
{
    /**
     * Local-only convenience: ensures a "dev@tododeloo.test" account exists and signs in as it.
     * Aborts in non-local environments — never exposed in production.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        abort_if(app()->environment('production'), 404);

        $user = User::firstOrCreate(
            ['email' => 'dev@tododeloo.test'],
            [
                'name' => 'Dev',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        auth()->login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('today.show'));
    }
}
