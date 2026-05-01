<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'can_score' => $user->canScore(),
            ],
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'can_score' => $user->canScore(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Re-authenticate the currently logged-in user by password.
     *
     * Used as a speed-bump for destructive actions (match complete, reopen,
     * corrections) when the match has no corrections_pin configured.
     *
     * Optionally verifies the user can manage a specific match (MD / owner /
     * org admin) when `match_id` is provided.
     *
     * POST /api/auth/verify-password
     * Body: { password: string, match_id?: int }
     *   200: { ok: true, user: { id, name, email } }
     *   401: { ok: false, error: 'Incorrect password' }
     *   403: { ok: false, error: 'You cannot manage this match' }
     */
    public function verifyPassword(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'match_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'ok' => false,
                'error' => 'Incorrect password.',
            ], 401);
        }

        if (! empty($validated['match_id'])) {
            $match = ShootingMatch::find($validated['match_id']);

            if (! $match) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Match not found.',
                ], 404);
            }

            $canManage = $user->isOwner()
                || $match->created_by === $user->id
                || ($match->organization && $user->isOrgAdmin($match->organization));

            if (! $canManage) {
                return response()->json([
                    'ok' => false,
                    'error' => 'You are not the match director or owner of this match.',
                ], 403);
            }
        }

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Convert a Sanctum API token into a web session (for WebView).
     * GET /app-login?token=xxx&redirect=/score
     */
    public function tokenLogin(Request $request)
    {
        $token = $request->query('token');
        $redirect = $request->query('redirect', '/');

        if (! $token) {
            return redirect('/login');
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (! $accessToken || ! $accessToken->tokenable) {
            return redirect('/login');
        }

        Auth::login($accessToken->tokenable);
        $request->session()->regenerate();

        return redirect($redirect);
    }
}
