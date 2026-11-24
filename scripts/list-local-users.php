<?php

/**
 * List the local dev users so we know which account to log in as.
 * Run: php scripts/list-local-users.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = \App\Models\User::query()
    ->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'match_director' THEN 1 ELSE 2 END")
    ->orderBy('id')
    ->limit(15)
    ->get(['id', 'name', 'email', 'role']);

printf("%-4s %-28s %-40s %s\n", 'ID', 'NAME', 'EMAIL', 'ROLE');
echo str_repeat('-', 90), "\n";
foreach ($users as $u) {
    printf("%-4s %-28s %-40s %s\n", $u->id, substr($u->name, 0, 28), substr($u->email, 0, 40), $u->role);
}
echo "\nTotal users: ".\App\Models\User::count()."\n";
