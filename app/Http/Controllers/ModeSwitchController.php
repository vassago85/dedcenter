<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModeSwitchController extends Controller
{
    /**
     * Switch the user's active "mode" (shooter / org / admin) and redirect
     * them to the home URL for that mode. Modes are filtered to what the
     * authenticated user actually has access to — we never let someone
     * land on a dashboard they can't see.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $mode = (string) $request->input('mode', '');
        $modes = user_available_modes($user);

        $target = collect($modes)->firstWhere('slug', $mode);
        if (! $target) {
            return redirect()->to(user_home_path($user));
        }

        session(['active_mode' => $target['slug']]);

        return redirect()->to($target['url']);
    }
}
