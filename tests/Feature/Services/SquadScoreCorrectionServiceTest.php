<?php

/**
 * SquadScoreCorrectionService is the business-logic core of the
 * post-squad correction editor. These tests pin down the diff rules
 * (create / update / delete / unchanged), the required correction
 * note, the per-match gong scoping, and the audit trail.
 */

use App\Enums\MatchStatus;
use App\Models\Gong;
use App\Models\Score;
use App\Models\ScoreAuditLog;
use App\Models\Shooter;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\TargetSet;
use App\Models\User;
use App\Services\SquadScoreCorrectionService;

/**
 * Build a standard match with a single 5-gong target set, one squad and
 * one shooter on it. Returns the assembled pieces so each test can
 * reach in and poke the bits it cares about.
 *
 * @return array{match: ShootingMatch, squad: Squad, shooter: Shooter, gongs: \Illuminate\Support\Collection<int, Gong>, actor: User}
 */
function buildStandardMatchForCorrections(bool $royalFlushEnabled = true): array
{
    $match = ShootingMatch::factory()->create([
        'status' => MatchStatus::Completed,
        'scoring_type' => 'standard',
        'royal_flush_enabled' => $royalFlushEnabled,
    ]);

    $targetSet = TargetSet::factory()->create([
        'match_id' => $match->id,
        'distance_meters' => 300,
        'label' => '300m',
        'sort_order' => 1,
    ]);

    $gongs = collect();
    for ($i = 1; $i <= 5; $i++) {
        $gongs->push(Gong::factory()->create([
            'target_set_id' => $targetSet->id,
            'number' => $i,
            'multiplier' => 1.0,
        ]));
    }

    $squad = Squad::factory()->create(['match_id' => $match->id]);
    $shooter = Shooter::factory()->create([
        'squad_id' => $squad->id,
        'user_id' => null,
    ]);

    return [
        'match' => $match,
        'squad' => $squad,
        'shooter' => $shooter,
        'gongs' => $gongs,
        'actor' => User::factory()->create(['role' => 'owner']),
    ];
}

it('creates new Score rows for cells flipped from null to hit/miss and logs an audit entry per write', function () {
    $ctx = buildStandardMatchForCorrections();

    $targets = [
        $ctx['shooter']->id => [
            $ctx['gongs'][0]->id => true,
            $ctx['gongs'][1]->id => false,
            $ctx['gongs'][2]->id => null, // untouched
            $ctx['gongs'][3]->id => null,
            $ctx['gongs'][4]->id => null,
        ],
    ];

    $stats = app(SquadScoreCorrectionService::class)->apply(
        $ctx['match'], $ctx['squad'], $targets, 'Paper book review — gongs 1 & 2.', $ctx['actor']->id,
    );

    expect($stats)->toBe(['created' => 2, 'updated' => 0, 'deleted' => 0, 'unchanged' => 3]);
    expect(Score::where('shooter_id', $ctx['shooter']->id)->count())->toBe(2);

    // Every created Score leaves at least one audit row with the note.
    $auditRows = ScoreAuditLog::where('match_id', $ctx['match']->id)->get();
    expect($auditRows->pluck('action')->all())->toContain('created');
    expect($auditRows->pluck('action')->all())->toContain('correction');
    expect($auditRows->where('action', 'correction')->first()->reason)->toBe('Paper book review — gongs 1 & 2.');
});

it('flips an existing Score when the desired state contradicts the recorded one', function () {
    $ctx = buildStandardMatchForCorrections();

    Score::create([
        'shooter_id' => $ctx['shooter']->id,
        'gong_id' => $ctx['gongs'][0]->id,
        'is_hit' => false,
        'recorded_by' => $ctx['actor']->id,
        'recorded_at' => now(),
    ]);

    $targets = [
        $ctx['shooter']->id => [
            $ctx['gongs'][0]->id => true, // flip miss → hit
        ],
    ];

    $stats = app(SquadScoreCorrectionService::class)->apply(
        $ctx['match'], $ctx['squad'], $targets, 'RO confirmed hit on replay.', $ctx['actor']->id,
    );

    expect($stats['updated'])->toBe(1);
    $score = Score::where('shooter_id', $ctx['shooter']->id)->first();
    expect((bool) $score->is_hit)->toBeTrue();

    $audit = ScoreAuditLog::where('match_id', $ctx['match']->id)->where('action', 'updated')->first();
    expect($audit)->not->toBeNull();
    expect($audit->reason)->toBe('RO confirmed hit on replay.');
});

it('deletes the Score row when desired state is null but a row exists', function () {
    $ctx = buildStandardMatchForCorrections();

    Score::create([
        'shooter_id' => $ctx['shooter']->id,
        'gong_id' => $ctx['gongs'][0]->id,
        'is_hit' => true,
        'recorded_by' => $ctx['actor']->id,
        'recorded_at' => now(),
    ]);

    $targets = [
        $ctx['shooter']->id => [
            $ctx['gongs'][0]->id => null,
        ],
    ];

    $stats = app(SquadScoreCorrectionService::class)->apply(
        $ctx['match'], $ctx['squad'], $targets, 'Double-counted shot — removing.', $ctx['actor']->id,
    );

    expect($stats['deleted'])->toBe(1);
    expect(Score::where('shooter_id', $ctx['shooter']->id)->count())->toBe(0);
    expect(ScoreAuditLog::where('match_id', $ctx['match']->id)->where('action', 'deleted')->count())->toBe(1);
});

it('leaves unchanged cells alone and never writes to the audit log for them', function () {
    $ctx = buildStandardMatchForCorrections();

    Score::create([
        'shooter_id' => $ctx['shooter']->id,
        'gong_id' => $ctx['gongs'][0]->id,
        'is_hit' => true,
        'recorded_by' => $ctx['actor']->id,
        'recorded_at' => now(),
    ]);

    $targets = [
        $ctx['shooter']->id => [
            $ctx['gongs'][0]->id => true, // same as existing
        ],
    ];

    $stats = app(SquadScoreCorrectionService::class)->apply(
        $ctx['match'], $ctx['squad'], $targets, 'No real change.', $ctx['actor']->id,
    );

    expect($stats)->toBe(['created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 1]);
    expect(ScoreAuditLog::where('match_id', $ctx['match']->id)->count())->toBe(0);
});

it('rejects an empty correction note', function () {
    $ctx = buildStandardMatchForCorrections();

    expect(fn () => app(SquadScoreCorrectionService::class)->apply(
        $ctx['match'], $ctx['squad'], [], '   ', $ctx['actor']->id,
    ))->toThrow(InvalidArgumentException::class, 'correction note is required');
});

it('refuses to touch a squad that belongs to a different match', function () {
    $ctx = buildStandardMatchForCorrections();
    $otherMatch = ShootingMatch::factory()->create(['scoring_type' => 'standard']);

    expect(fn () => app(SquadScoreCorrectionService::class)->apply(
        $otherMatch, $ctx['squad'], [], 'oops', $ctx['actor']->id,
    ))->toThrow(InvalidArgumentException::class, 'Squad does not belong to this match');
});

it('ignores shooter_ids that are not on the squad and gong_ids from another match', function () {
    $ctx = buildStandardMatchForCorrections();

    // A shooter on a DIFFERENT squad of the same match — should be ignored.
    $otherSquad = Squad::factory()->create(['match_id' => $ctx['match']->id]);
    $otherShooter = Shooter::factory()->create(['squad_id' => $otherSquad->id, 'user_id' => null]);

    // A gong from a totally different match — should be ignored.
    $otherMatch = ShootingMatch::factory()->create(['scoring_type' => 'standard']);
    $otherTs = TargetSet::factory()->create(['match_id' => $otherMatch->id]);
    $foreignGong = Gong::factory()->create(['target_set_id' => $otherTs->id, 'number' => 1]);

    $targets = [
        $ctx['shooter']->id => [$ctx['gongs'][0]->id => true],
        $otherShooter->id => [$ctx['gongs'][1]->id => true], // wrong squad — skipped
        $ctx['shooter']->id => [ // merged below because duplicate key above
            $ctx['gongs'][0]->id => true,
            $foreignGong->id => true, // foreign gong — skipped
        ],
    ];

    $stats = app(SquadScoreCorrectionService::class)->apply(
        $ctx['match'], $ctx['squad'], $targets, 'Filter check.', $ctx['actor']->id,
    );

    expect($stats['created'])->toBe(1); // only the one legal cell
    expect(Score::where('shooter_id', $otherShooter->id)->count())->toBe(0);
    expect(Score::where('gong_id', $foreignGong->id)->count())->toBe(0);
});
