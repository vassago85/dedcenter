<?php

use App\Models\MatchCategory;
use App\Models\MatchDivision;
use App\Models\MatchRegistration;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Services\MatchEntriesImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->match = ShootingMatch::factory()->active()->prs()->create(['created_by' => $this->admin->id]);
});

function entriesCsv(string $body): string
{
    $header = 'Squad,Bib,Name,Division,Category,Email,Phone,"Membership #","Has account"';

    return $header."\n".$body;
}

test('importer creates new users and confirmed registrations from CSV rows', function () {
    $csv = entriesCsv(<<<CSV
,,"Alice Adams",Open,Senior,alice@example.com,"+27 821111111",PPRC-0001,No
,,"Bob Burgers",Factory,Mens,bob@example.com,0825555555,PPRC-0002,No
CSV);

    $service = app(MatchEntriesImportService::class);
    $result = $service->import($this->match, $csv);

    expect($result['errors'])->toBe([]);
    expect($result['created_users'])->toBe(2);
    expect($result['existing_users'])->toBe(0);
    expect($result['created_registrations'])->toBe(2);
    expect($result['created_divisions'])->toBe(2);
    expect($result['created_categories'])->toBe(2);

    $alice = User::where('email', 'alice@example.com')->first();
    expect($alice)->not->toBeNull();
    expect($alice->email_verified_at)->toBeNull();
    expect($alice->name)->toBe('Alice Adams');

    $reg = MatchRegistration::where('match_id', $this->match->id)
        ->where('user_id', $alice->id)
        ->first();
    expect($reg)->not->toBeNull();
    expect($reg->payment_status)->toBe('confirmed');
    expect($reg->division->name)->toBe('Open');
    expect($reg->category->name)->toBe('Senior');
    expect($reg->contact_number)->toBe('27821111111');
});

test('importer dedupes existing users by email (case-insensitive)', function () {
    $charlie = User::factory()->create([
        'email' => 'charlie@example.com',
        'name' => 'Charlie',
    ]);

    $csv = entriesCsv(<<<CSV
,,"Charlie Existing",Open,Mens,Charlie@Example.com,"+27 833333333",PPRC-0003,Yes
CSV);

    $result = app(MatchEntriesImportService::class)->import($this->match, $csv);

    expect($result['errors'])->toBe([]);
    expect($result['created_users'])->toBe(0);
    expect($result['existing_users'])->toBe(1);
    expect(User::where('email', 'charlie@example.com')->count())->toBe(1);

    expect(in_array($charlie->id, $result['existing_user_ids'], true))->toBeTrue();
});

test('importer reuses existing divisions and categories (idempotent)', function () {
    $existingOpen = MatchDivision::create([
        'match_id' => $this->match->id,
        'name' => 'Open',
        'sort_order' => 1,
    ]);
    $existingSenior = MatchCategory::create([
        'match_id' => $this->match->id,
        'name' => 'Senior',
        'slug' => 'senior',
        'sort_order' => 1,
    ]);

    $csv = entriesCsv(<<<CSV
,,"Dee Dee",open,SENIOR,dee@example.com,"+27 844444444",PPRC-0004,No
CSV);

    $result = app(MatchEntriesImportService::class)->import($this->match, $csv);

    expect($result['errors'])->toBe([]);
    expect($result['created_divisions'])->toBe(0);
    expect($result['created_categories'])->toBe(0);

    $reg = MatchRegistration::where('match_id', $this->match->id)->first();
    expect($reg->division_id)->toBe($existingOpen->id);
    expect($reg->category_id)->toBe($existingSenior->id);
});

test('importer is idempotent across re-runs (updates instead of duplicating)', function () {
    $csv = entriesCsv(<<<CSV
,,"Eve Evans",Open,Mens,eve@example.com,0810000000,PPRC-0005,No
CSV);

    $service = app(MatchEntriesImportService::class);

    $first = $service->import($this->match, $csv);
    $second = $service->import($this->match, $csv);

    expect($first['created_registrations'])->toBe(1);
    expect($second['created_registrations'])->toBe(0);
    expect($second['updated_registrations'])->toBe(1);
    expect(MatchRegistration::where('match_id', $this->match->id)->count())->toBe(1);
});

test('importer skips rows missing name or email and reports warnings', function () {
    $csv = entriesCsv(<<<CSV
,,"",Open,Senior,nameless@example.com,,PPRC-XX,No
,,"No Email",Open,Senior,,,PPRC-YY,No
,,"Valid Person",Limited,Mens,valid@example.com,,PPRC-OK,No
CSV);

    $result = app(MatchEntriesImportService::class)->import($this->match, $csv);

    expect($result['skipped_rows'])->toBe(2);
    expect($result['created_users'])->toBe(1);
    expect($result['created_registrations'])->toBe(1);
});

test('importer flags FREE entry when --free option is passed', function () {
    $csv = entriesCsv(<<<CSV
,,"Free Frank",Open,Mens,frank@example.com,,PPRC-0007,No
CSV);

    $result = app(MatchEntriesImportService::class)->import($this->match, $csv, freeEntry: true);
    expect($result['errors'])->toBe([]);

    $reg = MatchRegistration::where('match_id', $this->match->id)->first();
    expect($reg->is_free_entry)->toBeTrue();
    expect((float) $reg->amount)->toBe(0.0);
});
