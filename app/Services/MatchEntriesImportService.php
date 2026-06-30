<?php

namespace App\Services;

use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Support\SpreadsheetNumberNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Imports an external "entries" CSV (e.g. the PretoriaPRC entries export) into
 * confirmed match registrations + real-email user accounts.
 *
 * Expected CSV header:
 *   Squad,Bib,Name,Division,Category,Email,Phone,"Membership #","Has account"
 *
 * Squad/Bib are ignored — squadding happens in DeadCenter once invitees
 * self-squad (or the MD assigns them). Division and Category are mapped to
 * per-match MatchDivision / MatchCategory rows (created on demand). Phone is
 * stored on the registration as `contact_number` (encrypted cast).
 */
final class MatchEntriesImportService
{
    /**
     * @return array{
     *     created_users: int,
     *     existing_users: int,
     *     created_registrations: int,
     *     updated_registrations: int,
     *     created_divisions: int,
     *     created_categories: int,
     *     skipped_rows: int,
     *     new_user_ids: list<int>,
     *     existing_user_ids: list<int>,
     *     errors: list<string>,
     *     warnings: list<string>,
     * }
     */
    public function import(
        ShootingMatch $match,
        string $csv,
        bool $freeEntry = false,
    ): array {
        $result = [
            'created_users' => 0,
            'existing_users' => 0,
            'created_registrations' => 0,
            'updated_registrations' => 0,
            'created_divisions' => 0,
            'created_categories' => 0,
            'skipped_rows' => 0,
            'new_user_ids' => [],
            'existing_user_ids' => [],
            'errors' => [],
            'warnings' => [],
        ];

        $rows = $this->parseCsv($csv);
        if (empty($rows)) {
            $result['errors'][] = 'CSV is empty or has no data rows.';

            return $result;
        }

        $header = array_shift($rows);
        $columnMap = $this->mapHeader($header);

        if ($columnMap['name'] === null || $columnMap['email'] === null) {
            $result['errors'][] = 'CSV header must contain at least Name and Email columns.';

            return $result;
        }

        $divisionCache = [];
        $categoryCache = [];
        $seenEmails = [];
        $rowNum = 1;

        DB::transaction(function () use (
            $match, $rows, $columnMap, $freeEntry,
            &$result, &$divisionCache, &$categoryCache, &$seenEmails, &$rowNum
        ) {
            foreach ($rows as $cols) {
                $rowNum++;

                $name = $this->cell($cols, $columnMap['name']);
                $email = strtolower(trim((string) $this->cell($cols, $columnMap['email']) ?? ''));

                if ($name === null || $name === '' || $email === '') {
                    $result['skipped_rows']++;
                    $result['warnings'][] = "Row {$rowNum}: missing name or email — skipped.";

                    continue;
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $result['skipped_rows']++;
                    $result['warnings'][] = "Row {$rowNum}: '{$email}' is not a valid email — skipped.";

                    continue;
                }

                if (isset($seenEmails[$email])) {
                    $result['warnings'][] = "Row {$rowNum}: duplicate email '{$email}' (also row {$seenEmails[$email]}); using latest row data.";
                }
                $seenEmails[$email] = $rowNum;

                $name = SpreadsheetNumberNormalizer::truncateField($name, 255) ?? $name;
                $divisionName = $this->cell($cols, $columnMap['division']);
                $categoryName = $this->cell($cols, $columnMap['category']);
                $phoneRaw = $this->cell($cols, $columnMap['phone']);
                $phoneDigits = SpreadsheetNumberNormalizer::normalizePhoneDigits($phoneRaw);
                $membership = SpreadsheetNumberNormalizer::truncateField($this->cell($cols, $columnMap['membership']), 80);

                $existingUser = User::whereRaw('LOWER(email) = ?', [$email])->first();
                if ($existingUser) {
                    if ($existingUser->name !== $name && $existingUser->name === null) {
                        $existingUser->update(['name' => $name]);
                    }
                    $user = $existingUser;
                    $result['existing_users']++;
                    $result['existing_user_ids'][] = $user->id;
                } else {
                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make(Str::random(40)),
                        'role' => 'shooter',
                        'accepted_terms_at' => now(),
                    ]);
                    $result['created_users']++;
                    $result['new_user_ids'][] = $user->id;
                }

                $divisionId = null;
                if ($divisionName !== null && $divisionName !== '') {
                    $divisionId = $this->ensureDivision($match, $divisionName, $divisionCache, $result);
                }

                $categoryId = null;
                if ($categoryName !== null && $categoryName !== '') {
                    $categoryId = $this->ensureCategory($match, $categoryName, $categoryCache, $result);
                }

                $adminNoteParts = ['Imported from entries CSV', "row {$rowNum}"];
                if ($membership !== null && $membership !== '') {
                    $adminNoteParts[] = "Membership #: {$membership}";
                }
                $adminNote = implode('. ', $adminNoteParts).'.';

                $regData = [
                    'payment_status' => 'confirmed',
                    'amount' => $freeEntry ? 0 : ($match->entry_fee ?? 0),
                    'is_free_entry' => $freeEntry,
                    'admin_notes' => SpreadsheetNumberNormalizer::truncateField($adminNote, 1000),
                    'contact_number' => $phoneDigits,
                    'division_id' => $divisionId,
                    'category_id' => $categoryId,
                ];

                $existing = MatchRegistration::where('match_id', $match->id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing) {
                    foreach (['division_id', 'category_id', 'contact_number', 'admin_notes'] as $key) {
                        if (($regData[$key] ?? null) === null && $existing->{$key} !== null) {
                            unset($regData[$key]);
                        }
                    }
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
            }
        });

        return $result;
    }

    /**
     * @return list<list<string>>
     */
    private function parseCsv(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $header
     * @return array{name:?int,email:?int,division:?int,category:?int,phone:?int,membership:?int}
     */
    private function mapHeader(array $header): array
    {
        $map = [
            'name' => null,
            'email' => null,
            'division' => null,
            'category' => null,
            'phone' => null,
            'membership' => null,
        ];

        foreach ($header as $idx => $col) {
            $key = strtolower(trim($col));
            $key = trim($key, " \t#");
            switch ($key) {
                case 'name':
                case 'full name':
                case 'shooter':
                    $map['name'] = $idx;
                    break;
                case 'email':
                case 'e-mail':
                    $map['email'] = $idx;
                    break;
                case 'division':
                    $map['division'] = $idx;
                    break;
                case 'category':
                case 'class':
                    $map['category'] = $idx;
                    break;
                case 'phone':
                case 'phone #':
                case 'mobile':
                case 'cell':
                    $map['phone'] = $idx;
                    break;
                case 'membership':
                case 'membership number':
                case 'member':
                case 'member id':
                case 'member number':
                    $map['membership'] = $idx;
                    break;
            }
        }

        return $map;
    }

    private function cell(array $cols, ?int $idx): ?string
    {
        if ($idx === null || ! array_key_exists($idx, $cols)) {
            return null;
        }
        $value = trim((string) $cols[$idx]);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string,int>  $cache
     */
    private function ensureDivision(ShootingMatch $match, string $name, array &$cache, array &$result): int
    {
        $key = strtolower($name);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $division = MatchDivision::where('match_id', $match->id)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();

        if (! $division) {
            $maxSort = MatchDivision::where('match_id', $match->id)->max('sort_order') ?? 0;
            $division = MatchDivision::create([
                'match_id' => $match->id,
                'name' => $name,
                'sort_order' => $maxSort + 1,
            ]);
            $result['created_divisions']++;
        }

        return $cache[$key] = $division->id;
    }

    /**
     * @param  array<string,int>  $cache
     */
    private function ensureCategory(ShootingMatch $match, string $name, array &$cache, array &$result): int
    {
        $key = strtolower($name);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $category = MatchCategory::where('match_id', $match->id)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();

        if (! $category) {
            $maxSort = MatchCategory::where('match_id', $match->id)->max('sort_order') ?? 0;
            $category = MatchCategory::create([
                'match_id' => $match->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'sort_order' => $maxSort + 1,
            ]);
            $result['created_categories']++;
        }

        return $cache[$key] = $category->id;
    }
}
