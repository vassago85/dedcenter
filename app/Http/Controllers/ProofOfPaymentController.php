<?php

namespace App\Http\Controllers;

use App\Models\MatchRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProofOfPaymentController extends Controller
{
    /**
     * Stream a registration's proof-of-payment document.
     *
     * These are bank statements / payment screenshots — PII that used to be
     * written to the PUBLIC disk and linked with `Storage::url()`, so anyone
     * who obtained (or guessed) the path could read another member's banking
     * document with no auth. Files now live on the private `local` disk and
     * are served only to:
     *   - platform admins,
     *   - an org admin of the match's organization (the registration desk),
     *   - the member who uploaded it.
     *
     * Falls back to the legacy `public` disk so documents uploaded before the
     * move still resolve.
     */
    public function show(Request $request, MatchRegistration $registration): Response
    {
        $user = $request->user();
        $match = $registration->match;

        $authorized = $user && (
            $user->isAdmin()
            || $registration->user_id === $user->id
            || ($match && $match->organization_id && $match->organization && $user->isOrgAdmin($match->organization))
        );

        abort_unless($authorized, 403, 'You are not authorized to view this document.');

        $path = $registration->proof_of_payment_path;
        abort_unless($path, 404, 'No proof of payment on file.');

        $disk = Storage::disk('local')->exists($path)
            ? 'local'
            : (Storage::disk('public')->exists($path) ? 'public' : null);

        abort_unless($disk, 404, 'Proof of payment file is missing.');

        return Storage::disk($disk)->response($path);
    }
}
