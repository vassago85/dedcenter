<?php

use App\Models\Organization;
use App\Models\Season;
use App\Models\Setting;
use App\Models\ShootingMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a member who is not staff gets a 403 on the organizer dashboard and a public link in the sidebar', function () {
    $org = Organization::factory()->create(['portal_enabled' => false]);
    $member = User::factory()->create();
    $org->admins()->attach($member->id, ['is_shooter' => true]);

    $this->actingAs($member)
        ->get(route('org.dashboard', $org))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('leaderboard', $org), false)
        ->assertDontSee(route('org.dashboard', $org), false)
        ->assertDontSee('Open scoring app')
        ->assertDontSee('Switch mode');
});

test('standings lists every organization the shooter belongs to', function () {
    $member = User::factory()->create();
    $first = Organization::factory()->create(['name' => 'Peregrine ELR']);
    $second = Organization::factory()->create(['name' => 'ALRHA Club']);
    $first->admins()->attach($member->id, ['is_shooter' => true]);
    $second->admins()->attach($member->id, ['is_shooter' => true]);

    $this->actingAs($member)
        ->get(route('standings'))
        ->assertOk()
        ->assertSee('Peregrine ELR')
        ->assertSee('ALRHA Club')
        ->assertSee(route('leaderboard', $first), false)
        ->assertSee(route('leaderboard', $second), false);
});

test('the scoring app is offered only for a ready or active match the user can run', function () {
    $org = Organization::factory()->create();
    $director = User::factory()->create();
    $ro = User::factory()->create();
    $org->admins()->attach($director->id, ['is_match_director' => true]);
    $org->admins()->attach($ro->id, ['is_range_officer' => true]);

    $match = ShootingMatch::factory()->active()->create([
        'organization_id' => $org->id,
        'created_by' => $director->id,
    ]);

    $this->actingAs($ro)->get(route('dashboard'))->assertOk()->assertDontSee('Open scoring app');
    $this->actingAs($ro)->getJson('/api/matches')->assertOk()->assertJsonCount(0, 'data');

    $match->staff()->attach($ro->id, ['role' => 'range_officer']);

    $this->actingAs($ro)->get(route('dashboard'))->assertOk()->assertSee('Open scoring app');
    $this->actingAs($ro)->getJson('/api/matches')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($director)->getJson('/api/matches')->assertOk()->assertJsonCount(1, 'data');
});

test('admin match edit only offers seasons from the match organization', function () {
    $org = Organization::factory()->create();
    $other = Organization::factory()->create();
    $admin = User::factory()->admin()->create();
    $match = ShootingMatch::factory()->create(['organization_id' => $org->id, 'created_by' => $admin->id]);

    $own = Season::create([
        'name' => 'Peregrine 2026',
        'year' => 2026,
        'organization_id' => $org->id,
    ]);
    $foreign = Season::create([
        'name' => 'Forster 2 Mile Challenge 2025/26',
        'year' => 2026,
        'organization_id' => $other->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.matches.edit', $match))
        ->assertOk()
        ->assertSee('Peregrine 2026')
        ->assertDontSee('Forster 2 Mile Challenge 2025/26');

    Livewire::actingAs($admin)
        ->test('admin.matches.edit', ['match' => $match])
        ->set('season_id', $foreign->id)
        ->call('save')
        ->assertHasErrors('season_id');

    expect($match->fresh()->season_id)->toBeNull();

    Livewire::actingAs($admin)
        ->test('admin.matches.edit', ['match' => $match])
        ->set('season_id', $own->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($match->fresh()->season_id)->toBe($own->id);
});

test('platform mail and bank secrets are write-only and owner-only', function () {
    Setting::set('mail_mailgun_secret', 'key-should-never-render-9f3a');
    Setting::set('mail_mailgun_domain', 'mg.example.com');
    Setting::set('bank_account_number', '6200999888');

    $owner = User::factory()->admin()->create();
    $staff = User::factory()->create(['role' => 'match_director']);

    $this->actingAs($owner)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertDontSee('key-should-never-render-9f3a')
        ->assertDontSee('6200999888')
        ->assertSee('Configured');

    $this->actingAs($staff)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertDontSee('Mailgun API Key')
        ->assertDontSee('key-should-never-render-9f3a');

    Livewire::actingAs($staff)
        ->test('admin.settings')
        ->call('saveMailSettings')
        ->assertStatus(403);

    Livewire::actingAs($owner)
        ->test('admin.settings')
        ->set('mail_mailgun_domain', 'mg.example.com')
        ->set('mail_mailgun_endpoint', 'api.mailgun.net')
        ->set('mail_from_address', 'noreply@example.com')
        ->set('mail_from_name', 'DeadCenter')
        ->set('mail_mailgun_secret', '')
        ->call('saveMailSettings')
        ->assertHasNoErrors();

    expect(Setting::get('mail_mailgun_secret'))->toBe('key-should-never-render-9f3a');
});

test('the team page uses one role and the organizer chrome has one exit', function () {
    $org = Organization::factory()->create(['name' => 'Peregrine']);
    $owner = User::factory()->create(['name' => 'Alex Owner']);
    $director = User::factory()->create();
    $org->admins()->attach($owner->id, ['is_owner' => true, 'is_match_director' => true, 'is_range_officer' => true]);
    $org->admins()->attach($director->id, ['is_match_director' => true, 'is_range_officer' => true]);

    $this->actingAs($owner)
        ->get(route('org.admins', $org))
        ->assertOk()
        ->assertSee('Owner')
        ->assertSee('Make owner')
        ->assertSee('Remove')
        ->assertSee('Director — runs every match')
        ->assertDontSee('>Shooter<', false);

    $this->get('/org/'.$org->slug.'/admins')->assertRedirect(route('org.admins', $org));

    $this->actingAs($director)
        ->get(route('org.dashboard', $org))
        ->assertOk()
        ->assertSee('Organizer')
        ->assertDontSee('Back to Shooter');

    Livewire::actingAs($owner)
        ->test('org.admins', ['organization' => $org])
        ->call('setRole', $director->id, 'range_officer')
        ->assertHasNoErrors();

    $pivot = $org->admins()->where('user_id', $director->id)->first()->pivot;
    expect((bool) $pivot->is_match_director)->toBeFalse()
        ->and((bool) $pivot->is_range_officer)->toBeTrue();
});
