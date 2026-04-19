<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as FortifyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Post-login redirect: send each user to their role-appropriate home.
 *
 * A shooter lands on the shooter dashboard. An org owner lands on the org
 * dashboard. A platform admin lands on the admin dashboard. Respects any
 * previously-chosen active mode (session-backed).
 */
class LoginResponse implements FortifyLoginResponse
{
    public function toResponse($request): Response
    {
        $target = user_home_path($request->user());

        return $request->wantsJson()
            ? response()->json(['two_factor' => false, 'redirect' => $target])
            : redirect()->intended($target);
    }
}
