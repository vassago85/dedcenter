<?php

/**
 * Reset a local dev user's password. Run:
 *   php scripts/reset-local-password.php paul@charsley.co.za password
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? 'paul@charsley.co.za';
$password = $argv[2] ?? 'password';

$user = \App\Models\User::where('email', $email)->first();
if (! $user) {
    fwrite(STDERR, "No user found with email {$email}\n");
    exit(1);
}

$user->password = \Illuminate\Support\Facades\Hash::make($password);
$user->save();

echo "OK — {$user->name} ({$user->email}) can now log in with password: {$password}\n";
echo "Login URL: ".config('app.url')."/login\n";
