<?php

namespace App\Services;

use App\Models\MatchRegistration;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Support\SpreadsheetNumberNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Imports tab-separated equipment registration rows (Royal Flush style) into
 * confirmed match registrations + placeholder shooter accounts.
 *
 * Column order (16): Timestamp, Name, Caliber, Bullet, Bullet weight, Action,
 * Barrel, Trigger, Chassis, Muzzle, Scope, Mount, Bipod, Phone, SA ID, Notes/Share.
 */
final class RoyalFlushEquipmentImportService
{
    private const COLS = 16;

    /**
     * @return array{
     *     created_users: int,
     *     created_registrations: int,
     *     updated_registrations: int,
     *     shooters_added: int,
     *     skipped_rows: int,
     *     errors: list<string>,
     *     warnings: list<string>,
     * }
     */
    public function import(
        ShootingMatch $match,
        string $tsv,
        bool $freeEntry = true,
        bool $addToDefaultSquad = true,
    ): array {
        $result = [
            'created_users' => 0,
            'created_registrations' => 0,
            'updated_registrations' => 0,
            'shooters_added' => 0,
            'skipped_rows' => 0,
            'errors' => [],
            'warnings' => [],
        ];

        $lines = preg_split('/\r\n|\r|\n/', $tsv) ?: [];
        $seenIdentity = [];
        $rowNum = 0;

        DB::transaction(function () use ($match, $lines, $freeEntry, $addToDefaultSquad, &$result, &$seenIdentity, &$rowNum) {
            foreach ($lines as $line) {
                $rowNum++;
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $cols = str_getcsv($line, "\t");
                if (count($cols) < 2) {
                    $result['skipped_rows']++;
                    $result['warnings'][] = "Row {$rowNum}: not enough columns (need tab-separated data).";

                    continue;
                }
                while (count($cols) < self::COLS) {
                    $cols[] = '';
                }

                $name = SpreadsheetNumberNormalizer::truncateField($cols[1] ?? '', 255);
                if ($name === null || $name === '') {
                    $result['skipped_rows']++;
                    $result['warnings'][] = "Row {$rowNum}: missing name.";

                    continue;
                }

                $saDigits = SpreadsheetNumberNormalizer::normalizeIdDigits($cols[14] ?? null);
                $phoneDigits = SpreadsheetNumberNormalizer::normalizePhoneDigits($cols[13] ?? null);

                $identity = $saDigits ?? $phoneDigits ?? 'name:'.strtolower($name);
                if (isset($seenIdentity[$identity])) {
                    $result['warnings'][] = "Row {$rowNum}: duplicate in file (same ID/phone/name as row {$seenIdentity[$identity]}); using latest row data for that person.";
                }
                $seenIdentity[$identity] = $rowNum;

                $email = $this->importEmailForRow($match, $saDigits, $name, $phoneDigits, $rowNum);

                $user = User::where('email', $email)->first();
                if (! $user) {
                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make(Str::random(40)),
                        'role' => 'shooter',
                        'accepted_terms_at' => now(),
                    ]);
                    // Not mass-assignable on User; must set explicitly so imports can log in after password reset.
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $result['created_users']++;
                } else {
                    if ($user->name !== $name) {
                        $user->update(['name' => $name]);
                    }
                }

                $timestampNote = SpreadsheetNumberNormalizer::truncateField($cols[0] ?? '', 80);
                $adminNote = 'Imported equipment sheet'.($timestampNote ? " (submitted {$timestampNote})" : '').". Source row {$rowNum}.";

                $regData = [
                    'payment_status' => 'confirmed',
                    'amount' => $freeEntry ? 0 : ($match->entry_fee ?? 0),
                    'is_free_entry' => $freeEntry,
                    'admin_notes' => $adminNote,
                    'sa_id_number' => $saDigits,
                    'caliber' => SpreadsheetNumberNormalizer::truncateField($cols[2] ?? null),
                    'bullet_brand_type' => SpreadsheetNumberNormalizer::truncateField($cols[3] ?? null),
                    'bullet_weight' => SpreadsheetNumberNormalizer::truncateField($cols[4] ?? null),
                    'action_brand' => SpreadsheetNumberNormalizer::truncateField($cols[5] ?? null),
                    'barrel_brand_length' => SpreadsheetNumberNormalizer::truncateField($cols[6] ?? null),
                    'trigger_brand' => SpreadsheetNumberNormalizer::truncateField($cols[7] ?? null),
                    'stock_chassis_brand' => SpreadsheetNumberNormalizer::truncateField($cols[8] ?? null),
                    'muzzle_brake_silencer_brand' => SpreadsheetNumberNormalizer::truncateField($cols[9] ?? null),
                    'scope_brand_type' => SpreadsheetNumberNormalizer::truncateField($cols[10] ?? null),
                    'scope_mount_brand' => SpreadsheetNumberNormalizer::truncateField($cols[11] ?? null),
                    'bipod_brand' => SpreadsheetNumberNormalizer::truncateField($cols[12] ?? null),
                    'contact_number' => $phoneDigits,
                    'share_rifle_with' => SpreadsheetNumberNormalizer::truncateField($cols[15] ?? null),
                ];

                $existing = MatchRegistration::where('match_id', $match->id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing) {
                    $existing->update($regData);
                    $result['updated_registrations']++;
                } else {
                    MatchRegistration::create([
                        'match_id' => $match->id,
                        'user_id' => $user->id,
                        'payment_reference' => MatchRegistration::generatePaymentReference($user),
                        ...$regData,
                    ]);
                    $result['created_registrations']++;
                }

                if ($addToDefaultSquad) {
                    $hasShooter = $match->shooters()->where('user_id', $user->id)->exists();
                    if (! $hasShooter) {
                        $squad = $match->squads()->firstOrCreate(
                            ['name' => 'Default'],
                            ['sort_order' => 0]
                        );
                        $maxSort = Shooter::where('squad_id', $squad->id)->max('sort_order') ?? 0;
                        Shooter::create([
                            'squad_id' => $squad->id,
                            'name' => $user->name,
                            'user_id' => $user->id,
                            'sort_order' => $maxSort + 1,
                            'status' => 'active',
                        ]);
                        $result['shooters_added']++;
                    }
                }
            }
        });

        return $result;
    }

    private function importEmailForRow(ShootingMatch $match, ?string $saIdDigits, string $name, ?string $phoneDigits, int $rowIndex): string
    {
        $suffix = User::IMPORT_PLACEHOLDER_EMAIL_SUFFIX;

        if ($saIdDigits !== null && $saIdDigits !== '') {
            return sprintf('rf.m%d.id%s%s', $match->id, $saIdDigits, $suffix);
        }

        if ($phoneDigits !== null && $phoneDigits !== '') {
            return sprintf('rf.m%d.p%s%s', $match->id, $phoneDigits, $suffix);
        }

        $hash = substr(hash('sha256', $match->id.'|'.$name.'|'.$rowIndex), 0, 20);

        return sprintf('rf.m%d.n%s%s', $match->id, $hash, $suffix);
    }
}
