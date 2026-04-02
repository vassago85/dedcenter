<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
use App\Models\MatchDivision;
use App\Models\MatchCategory;
use App\Models\TargetSet;
use App\Models\Gong;
use App\Models\Squad;
use App\Models\Shooter;
use App\Enums\MatchStatus;
use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Output\QRMarkupSVG;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    class extends Component {
    public Organization $organization;
    public ?ShootingMatch $match = null;

    public string $name = '';
    public string $date = '';
    public string $location = '';
    public string $notes = '';
    public string $public_bio = '';
    public string $entry_fee = '';
    public string $scoring_type = 'standard';
    public bool $scores_published = true;
    public int $concurrent_relays = 2;
    public string $corrections_pin = '';

    public string $staffEmail = '';

    public string $staffRole = 'range_officer';

    public string $tsDistance = '';
    public string $tsLabel = '';

    public string $gongNumber = '';
    public string $gongLabel = '';
    public string $gongMultiplier = '1.00';
    public string $gongDistance = '';
    public string $gongSize = '';
    public ?int $addingGongToTargetSetId = null;

    public string $squadName = '';

    public string $shooterName = '';
    public string $shooterBib = '';
    public ?int $addingShooterToSquadId = null;
    public ?int $shooterDivision = null;

    public string $divisionName = '';
    public string $categoryName = '';
    public array $shooterCategories = [];

    // ELR stage properties
    public string $elrStageLabel = '';
    public string $elrStageType = 'ladder';
    public ?int $editingElrStageId = null;

    public string $elrTargetName = '';
    public string $elrTargetDistance = '';
    public string $elrTargetBasePoints = '20';
    public string $elrTargetMaxShots = '3';
    public bool $elrTargetMustHit = true;
    public ?int $addingElrTargetToStageId = null;

    public string $elrProfileMultipliers = '1.00, 0.70, 0.50';

    public function mount(Organization $organization, ?ShootingMatch $match = null): void
    {
        $this->organization = $organization;

        if ($match && $match->exists) {
            if (! $match->userCanEditInOrg(auth()->user())) {
                abort(403, 'You are not authorized to edit this match.');
            }

            $this->match = $match;
            $this->name = $match->name;
            $this->date = $match->date?->format('Y-m-d') ?? '';
            $this->location = $match->location ?? '';
            $this->notes = $match->notes ?? '';
            $this->public_bio = $match->public_bio ?? '';
            $this->entry_fee = $match->entry_fee ? (string) $match->entry_fee : '';
            $this->scoring_type = $match->scoring_type ?? 'standard';
            $this->concurrent_relays = $match->concurrent_relays ?? 2;
            $this->scores_published = (bool) ($match->scores_published ?? true);
            $this->corrections_pin = $match->corrections_pin ?? '';
        } else {
            $this->entry_fee = $organization->entry_fee_default ? (string) $organization->entry_fee_default : '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'public_bio' => 'nullable|string|max:2000',
            'entry_fee' => 'nullable|numeric|min:0',
            'scoring_type' => 'required|in:standard,prs,elr',
            'corrections_pin' => 'nullable|string|min:4|max:6|regex:/^\d+$/',
        ]);

        $validated['entry_fee'] = $this->entry_fee !== '' ? (float) $this->entry_fee : null;
        $validated['concurrent_relays'] = $this->scoring_type === 'standard' ? max(1, $this->concurrent_relays) : 1;
        $validated['scores_published'] = $this->scores_published;
        $validated['corrections_pin'] = $this->corrections_pin !== '' ? $this->corrections_pin : null;

        if ($this->match) {
            $this->match->update($validated);
            Flux::toast('Match updated.', variant: 'success');
        } else {
            $this->match = ShootingMatch::create([
                ...$validated,
                'organization_id' => $this->organization->id,
                'created_by' => auth()->id(),
                'status' => MatchStatus::Draft,
            ]);
            Flux::toast('Match created.', variant: 'success');
            $this->redirect(route('org.matches.edit', [$this->organization, $this->match]), navigate: true);
        }
    }

    public function addMatchStaff(): void
    {
        if (! $this->match) {
            return;
        }

        $this->validate([
            'staffEmail' => 'required|email',
            'staffRole' => 'required|in:match_director,range_officer',
        ]);

        $user = User::where('email', $this->staffEmail)->first();
        if (! $user) {
            $this->addError('staffEmail', 'No user found with that email.');

            return;
        }

        if ($this->match->staff()->where('user_id', $user->id)->exists()) {
            $this->match->staff()->updateExistingPivot($user->id, ['role' => $this->staffRole]);
        } else {
            $this->match->staff()->attach($user->id, ['role' => $this->staffRole]);
        }

        $this->reset('staffEmail');
        $this->staffRole = 'range_officer';
        $this->match->load('staff');
        Flux::toast('Match team updated.', variant: 'success');
    }

    public function removeMatchStaff(int $userId): void
    {
        if (! $this->match) {
            return;
        }

        $this->match->staff()->detach($userId);
        $this->match->load('staff');
        Flux::toast('Removed from match team.', variant: 'success');
    }

    public function addTargetSet(): void
    {
        if ($this->scoring_type === 'prs') {
            $this->addPrsStage();
            return;
        }

        $this->validate(['tsDistance' => 'required|integer|min:1']);
        $distance = (int) $this->tsDistance;
        $maxSort = $this->match->targetSets()->max('sort_order') ?? 0;
        $this->match->targetSets()->create([
            'label' => "{$distance}m",
            'distance_meters' => $distance,
            'distance_multiplier' => $distance / 100,
            'sort_order' => $maxSort + 1,
        ]);
        $this->reset('tsDistance');
        Flux::toast('Target set added.', variant: 'success');
    }

    public function addPrsStage(): void
    {
        $this->validate(['tsLabel' => 'required|string|max:255']);

        $maxSort = $this->match->targetSets()->max('sort_order') ?? 0;
        $stageNumber = $maxSort + 1;

        $this->match->targetSets()->create([
            'label' => $this->tsLabel,
            'distance_meters' => 0,
            'distance_multiplier' => 1,
            'sort_order' => $stageNumber,
            'stage_number' => $stageNumber,
            'is_timed_stage' => false,
        ]);

        $this->reset('tsLabel');
        Flux::toast('PRS stage added.', variant: 'success');
    }

    public function updateTargetSet(int $id, string $field, string $value): void
    {
        $ts = TargetSet::where('id', $id)->where('match_id', $this->match->id)->firstOrFail();
        if ($field === 'distance_meters') {
            $distance = max(1, (int) $value);
            $ts->update(['distance_meters' => $distance, 'label' => "{$distance}m"]);
        } elseif ($field === 'distance_multiplier') {
            $ts->update(['distance_multiplier' => max(0.01, (float) $value)]);
        } elseif ($field === 'label') {
            $ts->update(['label' => $value]);
        } elseif ($field === 'is_timed_stage') {
            if ((bool) $value && $this->match->scoring_type === 'prs') {
                Flux::toast('For PRS, only the tiebreaker stage can be timed. Use the TB button instead.', variant: 'warning');
                return;
            }
            $ts->update(['is_timed_stage' => (bool) $value]);
        }
    }

    public function setTiebreakerStage(int $targetSetId): void
    {
        if ($this->match->scoring_type === 'prs') {
            $this->match->targetSets()->update(['is_tiebreaker' => false, 'is_timed_stage' => false]);
            TargetSet::where('id', $targetSetId)->where('match_id', $this->match->id)
                ->update(['is_tiebreaker' => true, 'is_timed_stage' => true]);
        } else {
            $this->match->targetSets()->update(['is_tiebreaker' => false]);
            TargetSet::where('id', $targetSetId)->where('match_id', $this->match->id)
                ->update(['is_tiebreaker' => true]);
        }
        Flux::toast('Tiebreaker stage set.', variant: 'success');
    }

    public function updateParTime(int $targetSetId, string $value): void
    {
        $ts = TargetSet::where('id', $targetSetId)->where('match_id', $this->match->id)->firstOrFail();
        $ts->update(['par_time_seconds' => $value !== '' ? max(0, (float) $value) : null]);
    }

    public function cloneTargetSet(int $id): void
    {
        $source = TargetSet::where('id', $id)->where('match_id', $this->match->id)->firstOrFail();
        $maxSort = $this->match->targetSets()->max('sort_order') ?? 0;
        $clone = $this->match->targetSets()->create([
            'label' => $source->label . ' (copy)',
            'distance_meters' => $source->distance_meters,
            'distance_multiplier' => $source->distance_multiplier,
            'sort_order' => $maxSort + 1,
            'stage_number' => $source->stage_number,
            'total_shots' => $source->total_shots,
            'is_timed_stage' => $source->is_timed_stage,
            'par_time_seconds' => $source->par_time_seconds,
        ]);
        foreach ($source->gongs as $gong) {
            $clone->gongs()->create([
                'number' => $gong->number,
                'label' => $gong->label,
                'multiplier' => $gong->multiplier,
                'distance_meters' => $gong->distance_meters,
                'target_size' => $gong->target_size,
            ]);
        }
        Flux::toast('Stage cloned.', variant: 'success');
    }

    public function deleteTargetSet(int $id): void
    {
        TargetSet::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Target set deleted.', variant: 'success');
    }

    public function populateStandardTargets(int $targetSetId): void
    {
        $standards = [
            ['number' => 1, 'label' => '2.5 MOA', 'multiplier' => '1.00'],
            ['number' => 2, 'label' => '2.0 MOA', 'multiplier' => '1.30'],
            ['number' => 3, 'label' => '1.5 MOA', 'multiplier' => '1.50'],
            ['number' => 4, 'label' => '1.0 MOA', 'multiplier' => '1.80'],
            ['number' => 5, 'label' => '0.5 MOA', 'multiplier' => '2.00'],
        ];
        $maxNumber = Gong::where('target_set_id', $targetSetId)->max('number') ?? 0;
        foreach ($standards as $s) {
            Gong::create(['target_set_id' => $targetSetId, 'number' => $maxNumber + $s['number'], 'label' => $s['label'], 'multiplier' => $s['multiplier']]);
        }
        Flux::toast('5 standard targets added.', variant: 'success');
    }

    public function populatePrsTargets(int $targetSetId, int $count = 5): void
    {
        $maxNumber = Gong::where('target_set_id', $targetSetId)->max('number') ?? 0;
        for ($i = 1; $i <= $count; $i++) {
            Gong::create([
                'target_set_id' => $targetSetId,
                'number' => $maxNumber + $i,
                'label' => "T" . ($maxNumber + $i),
                'multiplier' => '1.00',
            ]);
        }

        $this->syncPrsTargetCount($targetSetId);
        Flux::toast("{$count} PRS targets added.", variant: 'success');
    }

    private function syncPrsTargetCount(int $targetSetId): void
    {
        $count = Gong::where('target_set_id', $targetSetId)->count();
        TargetSet::where('id', $targetSetId)->update(['total_shots' => $count]);
    }

    public function startAddGong(int $targetSetId): void
    {
        $this->addingGongToTargetSetId = $targetSetId;
        $this->reset('gongNumber', 'gongLabel', 'gongMultiplier', 'gongDistance', 'gongSize');
        $this->gongMultiplier = '1.00';

        $maxNumber = Gong::where('target_set_id', $targetSetId)->max('number') ?? 0;
        $this->gongNumber = (string) ($maxNumber + 1);
    }

    public function addGong(): void
    {
        $isPrs = $this->scoring_type === 'prs';

        $rules = [
            'gongNumber' => 'required|integer|min:1',
            'gongLabel' => 'nullable|string|max:255',
        ];

        if ($isPrs) {
            $rules['gongDistance'] = 'nullable|integer|min:1';
            $rules['gongSize'] = 'nullable|string|max:50';
        } else {
            $rules['gongMultiplier'] = 'required|numeric|min:0.01';
        }

        $this->validate($rules);

        Gong::create([
            'target_set_id' => $this->addingGongToTargetSetId,
            'number' => (int) $this->gongNumber,
            'label' => $this->gongLabel ?: null,
            'multiplier' => $isPrs ? 1.00 : $this->gongMultiplier,
            'distance_meters' => $isPrs && $this->gongDistance ? (int) $this->gongDistance : null,
            'target_size' => $isPrs && $this->gongSize ? $this->gongSize : null,
        ]);

        if ($isPrs) {
            $this->syncPrsTargetCount($this->addingGongToTargetSetId);
        }

        $this->addingGongToTargetSetId = null;
        $this->reset('gongNumber', 'gongLabel', 'gongMultiplier', 'gongDistance', 'gongSize');
        Flux::toast('Target added.', variant: 'success');
    }

    public function updateGong(int $gongId, string $field, string $value): void
    {
        $gong = Gong::findOrFail($gongId);

        if ($field === 'label') {
            $gong->update(['label' => $value ?: null]);
        } elseif ($field === 'multiplier') {
            $gong->update(['multiplier' => max(0.01, (float) $value)]);
        } elseif ($field === 'distance_meters') {
            $gong->update(['distance_meters' => $value !== '' ? max(1, (int) $value) : null]);
        } elseif ($field === 'target_size') {
            $gong->update(['target_size' => $value ?: null]);
        }
    }

    public function deleteGong(int $id): void
    {
        $gong = Gong::findOrFail($id);
        $targetSetId = $gong->target_set_id;
        $gong->delete();

        if ($this->scoring_type === 'prs') {
            $this->syncPrsTargetCount($targetSetId);
        }

        Flux::toast('Target deleted.', variant: 'success');
    }

    // ── ELR Stages ──

    public function addElrStage(): void
    {
        $this->validate([
            'elrStageLabel' => 'required|string|max:255',
            'elrStageType' => 'required|in:static,ladder',
        ]);

        $profileMultipliers = array_map('floatval', array_map('trim', explode(',', $this->elrProfileMultipliers)));

        $profile = \App\Models\ElrScoringProfile::firstOrCreate(
            ['match_id' => $this->match->id, 'name' => 'Default'],
            ['multipliers' => $profileMultipliers]
        );

        if (!$this->match->elr_scoring_profile_id) {
            $this->match->update(['elr_scoring_profile_id' => $profile->id]);
        }

        $maxOrder = $this->match->elrStages()->max('sort_order') ?? 0;
        $this->match->elrStages()->create([
            'label' => $this->elrStageLabel,
            'stage_type' => $this->elrStageType,
            'elr_scoring_profile_id' => $profile->id,
            'sort_order' => $maxOrder + 1,
        ]);

        $this->elrStageLabel = '';
        $this->elrStageType = 'ladder';
        $this->match->refresh();
    }

    public function removeElrStage(int $stageId): void
    {
        $this->match->elrStages()->where('id', $stageId)->delete();
        $this->match->refresh();
    }

    public function addElrTarget(): void
    {
        $this->validate([
            'elrTargetName' => 'required|string|max:255',
            'elrTargetDistance' => 'required|integer|min:1',
            'elrTargetBasePoints' => 'required|numeric|min:0.01',
            'elrTargetMaxShots' => 'required|integer|min:1|max:20',
        ]);

        $stage = \App\Models\ElrStage::findOrFail($this->addingElrTargetToStageId);
        $maxOrder = $stage->targets()->max('sort_order') ?? 0;

        $stage->targets()->create([
            'name' => $this->elrTargetName,
            'distance_m' => (int) $this->elrTargetDistance,
            'base_points' => (float) $this->elrTargetBasePoints,
            'max_shots' => (int) $this->elrTargetMaxShots,
            'must_hit_to_advance' => $this->elrTargetMustHit,
            'sort_order' => $maxOrder + 1,
        ]);

        $this->elrTargetName = '';
        $this->elrTargetDistance = '';
        $this->elrTargetBasePoints = '20';
        $this->elrTargetMaxShots = '3';
        $this->elrTargetMustHit = true;
        $this->addingElrTargetToStageId = null;
        $this->match->refresh();
    }

    public function removeElrTarget(int $targetId): void
    {
        \App\Models\ElrTarget::where('id', $targetId)->delete();
        $this->match->refresh();
    }

    public function applyElrTemplate(): void
    {
        $profileMultipliers = [1.00, 0.70, 0.50];
        $profile = \App\Models\ElrScoringProfile::updateOrCreate(
            ['match_id' => $this->match->id, 'name' => 'Default'],
            ['multipliers' => $profileMultipliers]
        );
        $this->match->update(['elr_scoring_profile_id' => $profile->id]);

        $this->match->elrStages()->delete();

        $stage = $this->match->elrStages()->create([
            'label' => 'Stage 1',
            'stage_type' => 'ladder',
            'elr_scoring_profile_id' => $profile->id,
            'sort_order' => 1,
        ]);

        $targets = [
            ['name' => 'T1', 'distance_m' => 1000, 'base_points' => 10],
            ['name' => 'T2', 'distance_m' => 1200, 'base_points' => 15],
            ['name' => 'T3', 'distance_m' => 1500, 'base_points' => 20],
            ['name' => 'T4', 'distance_m' => 1800, 'base_points' => 25],
        ];

        foreach ($targets as $i => $t) {
            $stage->targets()->create([
                ...$t,
                'max_shots' => 3,
                'must_hit_to_advance' => true,
                'sort_order' => $i + 1,
            ]);
        }

        $this->elrProfileMultipliers = '1.00, 0.70, 0.50';
        $this->match->refresh();
    }

    public function updateElrProfile(): void
    {
        $multipliers = array_map('floatval', array_map('trim', explode(',', $this->elrProfileMultipliers)));

        $profile = $this->match->elrScoringProfile;
        if ($profile) {
            $profile->update(['multipliers' => $multipliers]);
        } else {
            $profile = \App\Models\ElrScoringProfile::create([
                'match_id' => $this->match->id,
                'name' => 'Default',
                'multipliers' => $multipliers,
            ]);
            $this->match->update(['elr_scoring_profile_id' => $profile->id]);
        }
        $this->match->refresh();
    }

    public function addSquad(): void
    {
        $this->validate(['squadName' => 'required|string|max:255']);
        $maxSort = $this->match->squads()->max('sort_order') ?? 0;
        $this->match->squads()->create(['name' => $this->squadName, 'sort_order' => $maxSort + 1]);
        $this->reset('squadName');
        Flux::toast('Squad added.', variant: 'success');
    }

    public function deleteSquad(int $id): void
    {
        Squad::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Squad deleted.', variant: 'success');
    }

    public function startAddShooter(int $squadId): void { $this->addingShooterToSquadId = $squadId; $this->reset('shooterName', 'shooterBib'); $this->shooterDivision = null; $this->shooterCategories = []; }

    public function addShooter(): void
    {
        $this->validate(['shooterName' => 'required|string|max:255', 'shooterBib' => 'nullable|string|max:50']);
        $maxSort = Shooter::where('squad_id', $this->addingShooterToSquadId)->max('sort_order') ?? 0;
        $shooter = Shooter::create([
            'squad_id' => $this->addingShooterToSquadId,
            'name' => $this->shooterName,
            'bib_number' => $this->shooterBib ?: null,
            'match_division_id' => $this->shooterDivision ?: null,
            'sort_order' => $maxSort + 1,
        ]);
        if (!empty($this->shooterCategories)) {
            $shooter->categories()->sync(array_map('intval', $this->shooterCategories));
        }
        $this->addingShooterToSquadId = null;
        $this->reset('shooterName', 'shooterBib');
        $this->shooterDivision = null;
        $this->shooterCategories = [];
        Flux::toast('Shooter added.', variant: 'success');
    }

    public function toggleShooterStatus(int $id): void
    {
        $shooter = Shooter::findOrFail($id);
        $shooter->update(['status' => $shooter->isActive() ? 'withdrawn' : 'active']);
        Flux::toast('Shooter ' . ($shooter->isActive() ? 'reactivated' : 'withdrawn') . '.', variant: 'success');
    }

    public function deleteShooter(int $id): void { Shooter::destroy($id); Flux::toast('Shooter removed permanently.', variant: 'success'); }

    public function moveShooter(int $shooterId, int $targetSquadId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $targetSquad = $this->match->squads()->findOrFail($targetSquadId);
        $maxSort = Shooter::where('squad_id', $targetSquadId)->max('sort_order') ?? 0;
        $shooter->update(['squad_id' => $targetSquadId, 'sort_order' => $maxSort + 1]);
        Flux::toast("Moved {$shooter->name} to {$targetSquad->name}.", variant: 'success');
    }

    public function updateShooterDivision(int $shooterId, string $value): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $shooter->update(['match_division_id' => $value !== '' ? (int) $value : null]);
    }

    // ── Divisions ──

    public function addDivision(): void
    {
        $this->validate(['divisionName' => 'required|string|max:255']);
        $maxSort = $this->match->divisions()->max('sort_order') ?? 0;
        $this->match->divisions()->create(['name' => $this->divisionName, 'sort_order' => $maxSort + 1]);
        $this->reset('divisionName');
        Flux::toast('Division added.', variant: 'success');
    }

    public function addMinorMajorPreset(): void
    {
        $maxSort = $this->match->divisions()->max('sort_order') ?? 0;
        $this->match->divisions()->create(['name' => 'Minor (.30 cal and below)', 'sort_order' => $maxSort + 1]);
        $this->match->divisions()->create(['name' => 'Major (above .30 cal)', 'sort_order' => $maxSort + 2]);
        Flux::toast('Minor/Major divisions added.', variant: 'success');
    }

    public function updateDivision(int $id, string $name): void
    {
        if (trim($name) === '') return;
        MatchDivision::where('id', $id)->where('match_id', $this->match->id)->update(['name' => trim($name)]);
    }

    public function deleteDivision(int $id): void
    {
        MatchDivision::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Division deleted.', variant: 'success');
    }

    public function addPrsDivisionPreset(): void
    {
        $maxSort = $this->match->divisions()->max('sort_order') ?? 0;
        $presets = [
            ['name' => 'Open', 'description' => 'Unrestricted equipment class'],
            ['name' => 'Factory', 'description' => 'Factory-stock rifle, no modifications'],
            ['name' => 'Limited', 'description' => 'Limited modifications allowed'],
        ];
        foreach ($presets as $i => $p) {
            $this->match->divisions()->create([...$p, 'sort_order' => $maxSort + $i + 1]);
        }
        Flux::toast('Open/Factory/Limited divisions added.', variant: 'success');
    }

    // ── Categories ──

    public function addCategory(): void
    {
        $this->validate(['categoryName' => 'required|string|max:255']);
        $maxSort = $this->match->categories()->max('sort_order') ?? 0;
        $slug = \Illuminate\Support\Str::slug($this->categoryName);
        $this->match->categories()->create(['name' => $this->categoryName, 'slug' => $slug, 'sort_order' => $maxSort + 1]);
        $this->reset('categoryName');
        Flux::toast('Category added.', variant: 'success');
    }

    public function addStandardCategoryPreset(): void
    {
        $maxSort = $this->match->categories()->max('sort_order') ?? 0;
        $presets = [
            ['name' => 'Overall', 'slug' => 'overall', 'description' => 'All shooters — default catch-all'],
            ['name' => 'Ladies', 'slug' => 'ladies', 'description' => 'Female shooters'],
            ['name' => 'Junior', 'slug' => 'junior', 'description' => 'Under 21 (centrefire) / Under 18 (rimfire) as of 1 Jan'],
            ['name' => 'Senior', 'slug' => 'senior', 'description' => 'Shooters 55+'],
        ];
        foreach ($presets as $i => $p) {
            $this->match->categories()->create([...$p, 'sort_order' => $maxSort + $i + 1]);
        }
        Flux::toast('Standard category presets added.', variant: 'success');
    }

    public function addPrsPresets(): void
    {
        $divSort = $this->match->divisions()->max('sort_order') ?? 0;
        $divisions = [
            ['name' => 'Open', 'description' => 'Unrestricted equipment class'],
            ['name' => 'Factory', 'description' => 'Factory-stock rifle, no modifications'],
            ['name' => 'Limited', 'description' => 'Limited modifications allowed'],
        ];
        foreach ($divisions as $i => $d) {
            $this->match->divisions()->firstOrCreate(['name' => $d['name'], 'match_id' => $this->match->id], [...$d, 'sort_order' => $divSort + $i + 1]);
        }

        $catSort = $this->match->categories()->max('sort_order') ?? 0;
        $categories = [
            ['name' => 'Overall', 'slug' => 'overall'],
            ['name' => 'Seniors Open', 'slug' => 'seniors-open'],
            ['name' => 'Ladies Open', 'slug' => 'ladies-open'],
            ['name' => 'Juniors Open', 'slug' => 'juniors-open'],
        ];
        foreach ($categories as $i => $c) {
            $this->match->categories()->firstOrCreate(['slug' => $c['slug'], 'match_id' => $this->match->id], [...$c, 'sort_order' => $catSort + $i + 1]);
        }

        Flux::toast('PRS divisions & categories added.', variant: 'success');
    }

    public function updateCategory(int $id, string $name): void
    {
        if (trim($name) === '') return;
        MatchCategory::where('id', $id)->where('match_id', $this->match->id)->update(['name' => trim($name)]);
    }

    public function deleteCategory(int $id): void
    {
        MatchCategory::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Category deleted.', variant: 'success');
    }

    public function updateShooterCategories(int $shooterId, array $categoryIds): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $validIds = $this->match->categories()->whereIn('id', array_map('intval', $categoryIds))->pluck('id')->toArray();
        $shooter->categories()->sync($validIds);
    }

    public function transitionStatus(string $target): void
    {
        $targetStatus = MatchStatus::from($target);
        if (! $this->match->status->canTransitionTo($targetStatus)) {
            Flux::toast('Invalid status transition.', variant: 'danger');
            return;
        }
        $this->match->update(['status' => $targetStatus]);
        Flux::toast("Match status changed to {$targetStatus->label()}.", variant: 'success');
    }

    public function reopenMatch(): void { $this->match->update(['status' => MatchStatus::Active]); Flux::toast('Match reopened.', variant: 'success'); }

    public function sendMatchReports(): void
    {
        $service = app(\App\Services\MatchReportService::class);
        $shooters = $service->getEmailableShooters($this->match);

        if ($shooters->isEmpty()) {
            Flux::toast('No shooters with linked email addresses found.', variant: 'warning');
            return;
        }

        foreach ($shooters as $shooter) {
            $report = $service->generateReport($this->match, $shooter);
            \Illuminate\Support\Facades\Mail::to($shooter->user->email)
                ->queue(new \App\Mail\ShooterMatchReport($report));
        }

        Flux::toast("Match reports queued for {$shooters->count()} shooters.", variant: 'success');
    }

    public function toggleScoresPublished(): void
    {
        $this->scores_published = ! $this->scores_published;
        if ($this->match) {
            $this->match->update(['scores_published' => $this->scores_published]);
            Flux::toast($this->scores_published ? 'Scores are now live.' : 'Scores hidden from public.', variant: 'success');
        }
    }

    public function with(): array
    {
        $data = ['divisions' => collect(), 'categories' => collect(), 'qrCodeSvg' => null];
        if ($this->match) {
            $data['targetSets'] = $this->match->targetSets()->with('gongs')->orderBy('sort_order')->get();
            $data['squads'] = $this->match->squads()->with(['shooters.division', 'shooters.categories'])->orderBy('sort_order')->get();
            $data['divisions'] = $this->match->divisions()->orderBy('sort_order')->get();
            $data['categories'] = $this->match->categories()->orderBy('sort_order')->get();

            if (in_array($this->match->status, [MatchStatus::Active, MatchStatus::Completed, MatchStatus::SquaddingOpen])) {
                $liveUrl = route('live', $this->match);
                $options = new QROptions(['outputInterface' => QRMarkupSVG::class, 'svgUseCssProperties' => false, 'scale' => 5]);
                $data['qrCodeSvg'] = (new QRCode($options))->render($liveUrl);
                $data['liveUrl'] = $liveUrl;
            }
        }
        return $data;
    }
}; ?>

<div class="space-y-8 max-w-4xl">
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('org.matches.index', $organization) }}" variant="ghost" size="sm">
            <svg class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            Back
        </flux:button>
        <div>
            <flux:heading size="xl">{{ $match ? 'Edit Match' : 'New Match' }}</flux:heading>
            <p class="mt-1 text-sm text-muted">{{ $organization->name }}</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Match Details</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Name" placeholder="e.g. Monthly Steel Challenge" required />
                <flux:input wire:model="date" label="Date" type="date" required />
            </div>
            <p class="text-xs text-muted">
                Matches still <strong>Active</strong> or <strong>Squadding Open</strong> are automatically set to <strong>Completed</strong> the day after this date ({{ config('app.timezone') }}). Update the date if the event moves.
            </p>
            <flux:input wire:model="location" label="Location" placeholder="e.g. Range 3, Pretoria" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model="entry_fee" label="Entry Fee (ZAR)" type="number" step="0.01" min="0" placeholder="Leave empty for free" />
                    <p class="mt-1 text-xs text-muted">Leave empty or 0 for free entry.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Scoring Type</label>
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('scoring_type', 'standard')"
                                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $scoring_type === 'standard' ? 'bg-accent text-primary' : 'bg-surface-2 text-secondary hover:bg-surface-2' }}">
                            Relay-Based
                        </button>
                        <button type="button" wire:click="$set('scoring_type', 'prs')"
                                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $scoring_type === 'prs' ? 'bg-amber-600 text-primary' : 'bg-surface-2 text-secondary hover:bg-surface-2' }}">
                            PRS
                        </button>
                        <button type="button" wire:click="$set('scoring_type', 'elr')"
                                class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $scoring_type === 'elr' ? 'bg-emerald-600 text-primary' : 'bg-surface-2 text-secondary hover:bg-surface-2' }}">
                            ELR
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-muted">
                        @if($scoring_type === 'prs')
                            PRS: Hit/miss (1pt each), shooter completes full stage, optional timer for tiebreaker.
                        @elseif($scoring_type === 'elr')
                            ELR: Extreme Long Range — shot-by-shot scoring with distance-based point values and optional ladder progression.
                        @else
                            Standard: Gong multipliers, relay-style scoring.
                        @endif
                    </p>
                </div>
            </div>
            @if($scoring_type === 'standard')
                <div class="rounded-lg border border-border bg-surface-2/30 p-4">
                    <div class="flex items-center gap-4">
                        <div class="w-32">
                            <label class="block text-sm font-medium text-secondary mb-1">Concurrent Relays</label>
                            <input type="number" wire:model="concurrent_relays" min="1" max="10"
                                   class="w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-primary text-center focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                        </div>
                        <p class="text-xs text-muted flex-1">How many relays shoot at the same time. Shared-rifle partners will be placed in different concurrent groups.</p>
                    </div>
                </div>
            @endif
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model="corrections_pin" label="Corrections PIN" type="text" maxlength="6" placeholder="4-6 digits (optional)" />
                    <p class="mt-1 text-xs text-muted">If set, this PIN is required for score corrections (reassign/move) on all scoring devices. Leave empty for no PIN requirement.</p>
                </div>
            </div>
            <flux:textarea wire:model="notes" label="Notes (internal)" placeholder="Staff-only notes — not shown on the public portal..." rows="3" />
            <flux:textarea wire:model="public_bio" label="Public event bio" placeholder="Short description for shooters — shown on the match page and portal..." rows="3" />
            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                    {{ $match ? 'Save Changes' : 'Create Match' }}
                </flux:button>
            </div>
        </div>
    </form>

    @if($match)
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Match Director &amp; range officers</h2>
            <p class="text-sm text-muted">Assign registered users for this event only. They can still shoot or hold roles elsewhere.</p>

            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-4 py-2 font-medium">Name</th>
                            <th class="px-4 py-2 font-medium">Email</th>
                            <th class="px-4 py-2 font-medium">Role</th>
                            <th class="px-4 py-2 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($match->staff as $member)
                            <tr wire:key="mstaff-{{ $member->id }}">
                                <td class="px-4 py-2 font-medium text-primary">{{ $member->name }}</td>
                                <td class="px-4 py-2 text-secondary">{{ $member->email }}</td>
                                <td class="px-4 py-2">
                                    @if($member->pivot->role === 'match_director')
                                        <flux:badge size="sm" color="blue">Match Director</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="green">Range Officer</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <flux:button size="sm" variant="ghost" class="!text-accent"
                                                 wire:click="removeMatchStaff({{ $member->id }})"
                                                 wire:confirm="Remove {{ $member->name }} from this match team?">
                                        Remove
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-muted text-sm">No one assigned yet. Add by email below.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form wire:submit="addMatchStaff" class="flex flex-wrap gap-3 items-end">
                <div class="min-w-[200px] flex-1">
                    <flux:input wire:model="staffEmail" label="User email" type="email" placeholder="registered@example.com" required />
                </div>
                <div class="w-44">
                    <label class="block text-sm font-medium text-secondary mb-1">Role</label>
                    <select wire:model="staffRole" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                        <option value="match_director">Match Director</option>
                        <option value="range_officer">Range Officer</option>
                    </select>
                </div>
                <flux:button type="submit" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add</flux:button>
            </form>
        </div>

        {{-- Status stepper --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-5">
            <h2 class="text-lg font-semibold text-primary">Match Lifecycle</h2>

            @php
                $steps = \App\Enums\MatchStatus::cases();
                $currentOrd = $match->status->ordinal();
            @endphp
            <div class="flex items-center gap-1 overflow-x-auto pb-2">
                @foreach($steps as $step)
                    @php $ord = $step->ordinal(); $isCurrent = $match->status === $step; $isPast = $ord < $currentOrd; @endphp
                    <div class="flex items-center gap-1 {{ $loop->last ? '' : 'flex-1' }}">
                        <div class="flex flex-col items-center min-w-[4.5rem]">
                            <div class="h-7 w-7 rounded-full flex items-center justify-center text-xs font-bold
                                {{ $isCurrent ? 'bg-'.$step->color().'-600 text-white ring-2 ring-'.$step->color().'-400' : ($isPast ? 'bg-green-600/30 text-green-400' : 'bg-surface-2 text-muted') }}">
                                @if($isPast) &#10003; @else {{ $ord + 1 }} @endif
                            </div>
                            <span class="mt-1 text-[10px] text-center leading-tight {{ $isCurrent ? 'font-bold text-primary' : 'text-muted' }}">{{ $step->label() }}</span>
                        </div>
                        @unless($loop->last)
                            <div class="flex-1 h-0.5 mt-[-0.75rem] {{ $isPast ? 'bg-green-600/40' : 'bg-surface-2' }}"></div>
                        @endunless
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-3 border-t border-border pt-4">
                @foreach($match->status->allowedTransitions() as $next)
                    <button wire:click="transitionStatus('{{ $next->value }}')"
                            wire:confirm="Change match status to {{ $next->label() }}?"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition-colors"
                            style="background: var(--color-{{ $next->color() }}-600, #6366f1);">
                        {{ $next->label() }}
                    </button>
                @endforeach
                @if($match->status === MatchStatus::Completed)
                    <flux:button wire:click="reopenMatch" variant="ghost" wire:confirm="Reopen this match?">Reopen Match</flux:button>
                @endif
            </div>

            <div class="flex flex-wrap gap-3 border-t border-border pt-4">
                <flux:button href="{{ route('org.matches.squadding', [$organization, $match]) }}" variant="ghost">Manage Squadding</flux:button>
                @if(in_array($match->status, [MatchStatus::Active, MatchStatus::Completed, MatchStatus::SquaddingOpen]))
                    <flux:button href="{{ route('score') }}" target="_blank" variant="ghost">Open Scoring</flux:button>
                    <flux:button href="{{ route('scoreboard', $match) }}" target="_blank" variant="ghost">View Scoreboard</flux:button>
                @endif
                @if(in_array($match->status, [MatchStatus::Active, MatchStatus::Completed]))
                    <flux:button href="{{ route('org.matches.export.standings', [$organization, $match]) }}" variant="ghost">Download Standings</flux:button>
                    <flux:button href="{{ route('org.matches.export.detailed', [$organization, $match]) }}" variant="ghost">Download Full Results</flux:button>
                    <flux:button wire:click="toggleScoresPublished" variant="{{ $scores_published ? 'ghost' : 'primary' }}" class="{{ $scores_published ? '' : '!bg-amber-600 hover:!bg-amber-700' }}">
                        {{ $scores_published ? 'Hide Scores' : 'Publish Scores' }}
                    </flux:button>
                @endif
                @if($match->status === MatchStatus::Completed)
                    <flux:button href="{{ route('org.matches.report.preview', [$organization, $match]) }}" target="_blank" variant="ghost">Preview Match Report</flux:button>
                    <flux:button wire:click="sendMatchReports" wire:confirm="Send match reports to all shooters with email addresses?" variant="primary" class="!bg-emerald-600 hover:!bg-emerald-700">
                        Send Match Reports
                    </flux:button>
                @endif
            </div>

            @if($qrCodeSvg)
                <div class="border-t border-border pt-4">
                    <h3 class="text-sm font-medium text-secondary mb-3">Live Scoreboard</h3>
                    <div class="flex items-start gap-4">
                        <div class="rounded-lg bg-white p-2 w-32 h-32 flex-shrink-0"><img src="{{ $qrCodeSvg }}" alt="QR Code" class="w-full h-full" /></div>
                        <div class="space-y-2">
                            <p class="text-xs text-muted">Share this QR code for spectators to follow scores live on their phones.</p>
                            <div class="flex items-center gap-2">
                                <input type="text" value="{{ $liveUrl }}" readonly class="flex-1 rounded-md border border-border bg-surface-2 px-3 py-1.5 text-xs text-secondary" />
                                <button onclick="navigator.clipboard.writeText('{{ $liveUrl }}'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000)"
                                        class="rounded-md bg-surface-2 px-3 py-1.5 text-xs font-medium text-primary hover:bg-surface-2">Copy</button>
                            </div>
                            <flux:button href="{{ route('live', $match) }}" target="_blank" variant="ghost" size="sm">Open Live Scoreboard</flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Match sponsors</h2>
            <p class="text-sm text-muted">Optional branding for this match. Leave each placement on the platform default or pick a sponsor that match directors may assign.</p>
            @include('partials.match-sponsor-assignment', ['match' => $match])
        </div>

        <flux:separator />

        {{-- Divisions --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-primary">Divisions</h2>
            @if($divisions->isNotEmpty())
                <div class="space-y-2">
                    @foreach($divisions as $div)
                        <div class="flex items-center gap-3 rounded-lg border border-border bg-surface px-4 py-2" wire:key="div-{{ $div->id }}">
                            <input type="text" value="{{ $div->name }}"
                                   class="flex-1 rounded-md border border-border bg-surface-2 px-3 py-1.5 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                   wire:change="updateDivision({{ $div->id }}, $event.target.value)" />
                            <span class="text-xs text-muted">{{ $div->shooters()->count() }} shooters</span>
                            <button class="text-accent hover:text-accent text-lg leading-none" wire:click="deleteDivision({{ $div->id }})" wire:confirm="Delete this division?">&times;</button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-muted">No divisions. All shooters compete in a single combined pool.</p>
            @endif
            <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4 space-y-3">
                <div class="flex gap-3 items-end">
                    <div class="flex-1"><flux:input wire:model="divisionName" placeholder="e.g. Open, Production..." /></div>
                    <flux:button wire:click="addDivision" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add Division</flux:button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="addMinorMajorPreset" size="sm" variant="ghost">+ Minor/Major</flux:button>
                    <flux:button wire:click="addPrsDivisionPreset" size="sm" variant="ghost">+ Open/Factory/Limited</flux:button>
                </div>
                <p class="text-[10px] text-muted/60">Divisions classify by equipment class. Single-select per shooter.</p>
            </div>
        </div>

        {{-- Categories --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-primary">Categories</h2>
            @if($categories->isNotEmpty())
                <div class="space-y-2">
                    @foreach($categories as $cat)
                        <div class="flex items-center gap-3 rounded-lg border border-border bg-surface px-4 py-2" wire:key="cat-{{ $cat->id }}">
                            <input type="text" value="{{ $cat->name }}"
                                   class="flex-1 rounded-md border border-border bg-surface-2 px-3 py-1.5 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                   wire:change="updateCategory({{ $cat->id }}, $event.target.value)" />
                            @if($cat->description)
                                <span class="text-[10px] text-muted hidden sm:inline">{{ $cat->description }}</span>
                            @endif
                            <span class="text-xs text-muted">{{ $cat->shooters()->count() }}</span>
                            <button class="text-accent hover:text-accent text-lg leading-none" wire:click="deleteCategory({{ $cat->id }})" wire:confirm="Delete this category?">&times;</button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-muted">No categories. Add demographic categories like Overall, Ladies, Junior, Senior.</p>
            @endif
            <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4 space-y-3">
                <div class="flex gap-3 items-end">
                    <div class="flex-1"><flux:input wire:model="categoryName" placeholder="e.g. Ladies, Junior..." /></div>
                    <flux:button wire:click="addCategory" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add Category</flux:button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="addStandardCategoryPreset" size="sm" variant="ghost">+ Standard Preset (Overall/Ladies/Junior/Senior)</flux:button>
                </div>
                <p class="text-[10px] text-muted/60">Categories classify by demographics. Multi-select per shooter (a score appears in all matching category leaderboards).</p>
            </div>
        </div>

        @if($scoring_type === 'prs')
        <div class="rounded-xl border border-amber-500/30 bg-amber-900/10 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-amber-400">PRS Quick Setup</h3>
                    <p class="text-xs text-muted">Adds divisions (Open/Factory/Limited) and categories (Overall/Seniors Open/Ladies Open/Juniors Open) in one click.</p>
                </div>
                <flux:button wire:click="addPrsPresets" size="sm" variant="primary" class="!bg-amber-600 hover:!bg-amber-700">+ PRS Presets</flux:button>
            </div>
        </div>
        @endif

        <flux:separator />

        {{-- Target Sets — Standard scoring --}}
        @if($scoring_type === 'standard')
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-primary">Target Sets</h2>

            @foreach($targetSets as $ts)
                <div class="rounded-xl border border-border bg-surface overflow-hidden" wire:key="ts-{{ $ts->id }}">
                    <div class="flex items-center justify-between border-b border-border px-6 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1">
                                <input type="number" value="{{ $ts->distance_meters }}" min="1"
                                       class="w-20 rounded-md border border-border bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                       wire:change="updateTargetSet({{ $ts->id }}, 'distance_meters', $event.target.value)" />
                                <span class="text-sm text-muted">m</span>
                            </div>
                            <div class="flex items-center gap-1" title="Distance multiplier applied to all gong scores">
                                <span class="text-xs text-muted">&times;</span>
                                <input type="number" value="{{ $ts->distance_multiplier ?? 1 }}" step="0.01" min="0.01"
                                       class="w-16 rounded-md border border-border bg-surface-2 px-2 py-1 text-sm text-amber-400 text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                       wire:change="updateTargetSet({{ $ts->id }}, 'distance_multiplier', $event.target.value)" />
                            </div>
                            <span class="text-xs text-muted">({{ $ts->gongs->count() }} targets)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="ghost" wire:click="cloneTargetSet({{ $ts->id }})">Clone</flux:button>
                            <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent"
                                         wire:click="deleteTargetSet({{ $ts->id }})"
                                         wire:confirm="Delete this target set and all its gongs?">Delete</flux:button>
                        </div>
                    </div>

                    <div class="p-4 space-y-3">
                        @if($ts->gongs->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-muted border-b border-border/50">
                                            <th class="px-3 py-2 font-medium w-12">#</th>
                                            <th class="px-3 py-2 font-medium">Label</th>
                                            <th class="px-3 py-2 font-medium w-28">Multiplier</th>
                                            <th class="px-3 py-2 font-medium w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50">
                                        @foreach($ts->gongs->sortBy('number') as $gong)
                                            <tr wire:key="gong-{{ $gong->id }}">
                                                <td class="px-3 py-1.5 text-muted font-mono">{{ $gong->number }}</td>
                                                <td class="px-3 py-1.5">
                                                    <input type="text" value="{{ $gong->label }}" placeholder="e.g. 2.5 MOA"
                                                           class="w-full rounded border border-border bg-surface-2 px-2 py-1 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                                           wire:change="updateGong({{ $gong->id }}, 'label', $event.target.value)" />
                                                </td>
                                                <td class="px-3 py-1.5">
                                                    <div class="flex items-center gap-1">
                                                        <input type="number" value="{{ $gong->multiplier }}" step="0.01" min="0.01"
                                                               class="w-20 rounded border border-border bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                                               wire:change="updateGong({{ $gong->id }}, 'multiplier', $event.target.value)" />
                                                        <span class="text-muted text-xs">x</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-1.5 text-right">
                                                    <button class="text-accent hover:text-accent text-lg leading-none"
                                                            wire:click="deleteGong({{ $gong->id }})">&times;</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-muted px-3">No targets yet.</p>
                        @endif

                        @if($addingGongToTargetSetId === $ts->id)
                            <div class="rounded-lg border border-border bg-surface-2/50 p-4 space-y-3">
                                <div class="grid grid-cols-3 gap-3">
                                    <flux:input wire:model="gongNumber" label="#" type="number" min="1" required />
                                    <flux:input wire:model="gongLabel" label="Label" placeholder="e.g. 1.5 MOA" />
                                    <flux:input wire:model="gongMultiplier" label="Multiplier" type="number" step="0.01" min="0.01" required />
                                </div>
                                <div class="flex gap-2">
                                    <flux:button wire:click="addGong" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add Target</flux:button>
                                    <flux:button wire:click="$set('addingGongToTargetSetId', null)" size="sm" variant="ghost">Cancel</flux:button>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2 px-3">
                                @if($ts->gongs->isEmpty())
                                    <flux:button size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover"
                                                 wire:click="populateStandardTargets({{ $ts->id }})">
                                        + Add Standard Targets (5 MOA)
                                    </flux:button>
                                @endif
                                <flux:button size="sm" variant="ghost" wire:click="startAddGong({{ $ts->id }})">+ Add Custom Target</flux:button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4 space-y-3">
                <h3 class="text-sm font-medium text-secondary">Add Target Set</h3>
                <div class="flex gap-3 items-end">
                    <div class="w-32">
                        <flux:input wire:model="tsDistance" label="Distance (m)" type="number" min="1" placeholder="e.g. 100" />
                    </div>
                    <flux:button wire:click="addTargetSet" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add Target Set</flux:button>
                </div>
            </div>
        </div>
        @endif

        {{-- PRS Stages --}}
        @if($scoring_type === 'prs' && $match)
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-primary">PRS Stages</h2>
            <p class="text-xs text-muted">Each stage has its own targets. 1 impact = 1 point. Targets can be at different distances.</p>

            @foreach($targetSets as $ts)
                <div class="rounded-xl border {{ $ts->is_tiebreaker ? 'border-amber-500/50 ring-1 ring-amber-500/20' : 'border-border' }} bg-surface overflow-hidden" wire:key="ts-{{ $ts->id }}">
                    {{-- Stage header --}}
                    <div class="flex items-center justify-between border-b border-border px-6 py-3">
                        <div class="flex items-center gap-3">
                            <input type="text" value="{{ $ts->label }}" placeholder="Stage name"
                                   class="w-64 rounded-md border border-border bg-surface-2 px-3 py-1 text-sm font-semibold text-primary focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                   wire:change="updateTargetSet({{ $ts->id }}, 'label', $event.target.value)" />
                            <span class="text-xs text-muted">({{ $ts->gongs->count() }} targets)</span>
                            @if($ts->is_tiebreaker)
                                <span class="rounded bg-amber-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-primary">Tiebreaker</span>
                                <span class="text-[10px] text-amber-400/80">Timed &mdash; most impacts wins, time separates equal scores</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="setTiebreakerStage({{ $ts->id }})"
                                    class="rounded-lg px-2 py-1 text-xs font-medium transition-colors {{ $ts->is_tiebreaker ? 'bg-amber-600 text-primary' : 'bg-surface-2 text-muted hover:text-primary' }}"
                                    title="Set as tiebreaker stage">&#9201; TB</button>
                            <flux:button size="sm" variant="ghost" wire:click="cloneTargetSet({{ $ts->id }})">Clone</flux:button>
                            <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent"
                                         wire:click="deleteTargetSet({{ $ts->id }})"
                                         wire:confirm="Delete this stage and all its targets?">Delete</flux:button>
                        </div>
                    </div>

                    {{-- Par time & timed toggle --}}
                    <div class="flex items-center gap-4 border-b border-border/50 bg-surface/50 px-6 py-2">
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" {{ $ts->is_timed_stage ? 'checked' : '' }}
                                   @if($this->match->scoring_type === 'prs') disabled title="PRS: timed is set automatically on the tiebreaker stage" @endif
                                   wire:change="updateTargetSet({{ $ts->id }}, 'is_timed_stage', $event.target.checked ? '1' : '0')"
                                   class="rounded border-border bg-surface-2 text-amber-600 focus:ring-amber-500 disabled:opacity-40 disabled:cursor-not-allowed" />
                            <span class="text-muted font-medium">Timed stage</span>
                            @if($this->match->scoring_type === 'prs')
                                <span class="text-[10px] text-slate-500">(auto: tiebreaker only)</span>
                            @endif
                        </label>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-muted whitespace-nowrap">Par Time (s):</label>
                            <input type="number" value="{{ $ts->par_time_seconds }}" step="0.01" min="0" placeholder="Optional"
                                   class="w-28 rounded-md border border-border bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                   wire:change="updateParTime({{ $ts->id }}, $event.target.value)" />
                        </div>
                    </div>

                    {{-- PRS target table --}}
                    <div class="p-4 space-y-3">
                        @if($ts->gongs->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-muted border-b border-border/50">
                                            <th class="px-3 py-2 font-medium w-12">#</th>
                                            <th class="px-3 py-2 font-medium">Name</th>
                                            <th class="px-3 py-2 font-medium w-28">Distance (m)</th>
                                            <th class="px-3 py-2 font-medium w-28">Size</th>
                                            <th class="px-3 py-2 font-medium w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50">
                                        @foreach($ts->gongs->sortBy('number') as $gong)
                                            <tr wire:key="gong-{{ $gong->id }}">
                                                <td class="px-3 py-1.5 text-muted font-mono">{{ $gong->number }}</td>
                                                <td class="px-3 py-1.5">
                                                    <input type="text" value="{{ $gong->label }}" placeholder="e.g. T1"
                                                           class="w-full rounded border border-border bg-surface-2 px-2 py-1 text-sm text-primary placeholder-muted focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                                           wire:change="updateGong({{ $gong->id }}, 'label', $event.target.value)" />
                                                </td>
                                                <td class="px-3 py-1.5">
                                                    <input type="number" value="{{ $gong->distance_meters }}" min="1" placeholder="m"
                                                           class="w-24 rounded border border-border bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                                           wire:change="updateGong({{ $gong->id }}, 'distance_meters', $event.target.value)" />
                                                </td>
                                                <td class="px-3 py-1.5">
                                                    <input type="text" value="{{ $gong->target_size }}" placeholder="e.g. 2 MOA"
                                                           class="w-24 rounded border border-border bg-surface-2 px-2 py-1 text-sm text-primary text-center placeholder-muted focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                                           wire:change="updateGong({{ $gong->id }}, 'target_size', $event.target.value)" />
                                                </td>
                                                <td class="px-3 py-1.5 text-right">
                                                    <button class="text-accent hover:text-accent text-lg leading-none"
                                                            wire:click="deleteGong({{ $gong->id }})">&times;</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-muted px-3">No targets yet.</p>
                        @endif

                        @if($addingGongToTargetSetId === $ts->id)
                            <div class="rounded-lg border border-border bg-surface-2/50 p-4 space-y-3">
                                <div class="grid grid-cols-4 gap-3">
                                    <flux:input wire:model="gongNumber" label="#" type="number" min="1" required />
                                    <flux:input wire:model="gongLabel" label="Name" placeholder="e.g. T1" />
                                    <flux:input wire:model="gongDistance" label="Distance (m)" type="number" min="1" placeholder="e.g. 400" />
                                    <flux:input wire:model="gongSize" label="Size" placeholder="e.g. 2 MOA" />
                                </div>
                                <div class="flex gap-2">
                                    <flux:button wire:click="addGong" size="sm" variant="primary" class="!bg-amber-600 hover:!bg-amber-700">Add Target</flux:button>
                                    <flux:button wire:click="$set('addingGongToTargetSetId', null)" size="sm" variant="ghost">Cancel</flux:button>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2 px-3">
                                @if($ts->gongs->isEmpty())
                                    <flux:button size="sm" variant="primary" class="!bg-amber-600 hover:!bg-amber-700" wire:click="populatePrsTargets({{ $ts->id }}, 5)">+ 5 Targets</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="populatePrsTargets({{ $ts->id }}, 8)">+ 8 Targets</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="populatePrsTargets({{ $ts->id }}, 10)">+ 10 Targets</flux:button>
                                @endif
                                <flux:button size="sm" variant="ghost" wire:click="startAddGong({{ $ts->id }})">+ Add Target</flux:button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- Add PRS Stage --}}
            <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4 space-y-3">
                <h3 class="text-sm font-medium text-secondary">Add Stage</h3>
                <div class="flex gap-3 items-end">
                    <div class="flex-1 max-w-sm">
                        <flux:input wire:model="tsLabel" label="Stage Name" placeholder="e.g. Stage 1 — Positional" />
                    </div>
                    <flux:button wire:click="addTargetSet" size="sm" variant="primary" class="!bg-amber-600 hover:!bg-amber-700">Add Stage</flux:button>
                </div>
            </div>
        </div>
        @endif

        {{-- ELR Stages (ELR only) --}}
        @if($scoring_type === 'elr' && $match)
        <div class="space-y-6" wire:key="elr-stages">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-primary">ELR Stages</h2>
                <button wire:click="applyElrTemplate" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">
                    Apply Default Template
                </button>
            </div>

            {{-- Scoring profile --}}
            <div class="rounded-xl border border-border bg-surface p-4">
                <h3 class="mb-2 text-sm font-semibold text-secondary uppercase tracking-wider">Scoring Profile</h3>
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <label class="block text-xs text-muted mb-1">Shot multipliers (comma-separated)</label>
                        <input type="text" wire:model="elrProfileMultipliers" placeholder="1.00, 0.70, 0.50"
                               class="w-full rounded-lg border border-border bg-app px-3 py-2 text-sm text-primary placeholder-muted focus:border-accent focus:outline-none" />
                    </div>
                    <button wire:click="updateElrProfile" class="rounded-lg bg-accent px-3 py-2 text-sm font-medium text-white hover:bg-accent-hover">
                        Save Profile
                    </button>
                </div>
                <p class="mt-1 text-xs text-muted">Shot 1 = first value, Shot 2 = second value, etc. Values are multiplied by target base points.</p>
                @if($match->elrScoringProfile)
                    <p class="mt-2 text-xs text-secondary">Active: {{ $match->elrScoringProfile->name }} — [{{ implode(', ', $match->elrScoringProfile->multipliers) }}]</p>
                @endif
            </div>

            {{-- Existing stages --}}
            @foreach($match->elrStages()->with('targets')->orderBy('sort_order')->get() as $stage)
            <div class="rounded-xl border {{ $stage->stage_type->value === 'ladder' ? 'border-emerald-500/50' : 'border-border' }} bg-surface overflow-hidden" wire:key="elr-stage-{{ $stage->id }}">
                <div class="flex items-center justify-between border-b border-border px-6 py-3">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-primary">{{ $stage->label }}</span>
                        <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $stage->stage_type->value === 'ladder' ? 'bg-emerald-600 text-white' : 'bg-surface-2 text-muted' }}">
                            {{ $stage->stage_type->value }}
                        </span>
                        <span class="text-xs text-muted">({{ $stage->targets->count() }} targets)</span>
                    </div>
                    <flux:button wire:click="removeElrStage({{ $stage->id }})" wire:confirm="Delete this stage and all its targets?" size="xs" variant="ghost" class="!text-red-400">
                        Delete Stage
                    </flux:button>
                </div>

                {{-- Targets --}}
                <div class="divide-y divide-border/50">
                    @forelse($stage->targets as $target)
                    <div class="flex items-center justify-between px-6 py-2">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-primary">{{ $target->name }}</span>
                            <span class="text-xs text-muted">{{ $target->distance_m }}m</span>
                            <span class="text-xs text-secondary">{{ $target->base_points }} pts</span>
                            <span class="text-xs text-muted">{{ $target->max_shots }} shots</span>
                            @if($target->must_hit_to_advance)
                                <span class="rounded bg-amber-600/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-400 uppercase">Must Hit</span>
                            @endif
                        </div>
                        <button wire:click="removeElrTarget({{ $target->id }})" wire:confirm="Remove this target?" class="text-xs text-red-400 hover:text-red-300">&times;</button>
                    </div>
                    @empty
                    <div class="px-6 py-3 text-sm text-muted">No targets yet.</div>
                    @endforelse
                </div>

                {{-- Add target form --}}
                @if($addingElrTargetToStageId === $stage->id)
                <div class="border-t border-border bg-surface-2/30 px-6 py-3">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div>
                            <label class="block text-xs text-muted mb-1">Name</label>
                            <input type="text" wire:model="elrTargetName" placeholder="T1" class="w-full rounded-lg border border-border bg-app px-2 py-1.5 text-sm text-primary placeholder-muted focus:border-accent focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs text-muted mb-1">Distance (m)</label>
                            <input type="number" wire:model="elrTargetDistance" placeholder="1000" min="1" class="w-full rounded-lg border border-border bg-app px-2 py-1.5 text-sm text-primary placeholder-muted focus:border-accent focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs text-muted mb-1">Base Points</label>
                            <input type="number" wire:model="elrTargetBasePoints" placeholder="20" step="0.01" min="0.01" class="w-full rounded-lg border border-border bg-app px-2 py-1.5 text-sm text-primary placeholder-muted focus:border-accent focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs text-muted mb-1">Max Shots</label>
                            <input type="number" wire:model="elrTargetMaxShots" placeholder="3" min="1" max="20" class="w-full rounded-lg border border-border bg-app px-2 py-1.5 text-sm text-primary placeholder-muted focus:border-accent focus:outline-none" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="elrTargetMustHit" class="rounded border-border bg-app text-accent focus:ring-accent" />
                                <span class="text-xs text-secondary">Must Hit</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button wire:click="addElrTarget" class="rounded-lg bg-accent px-3 py-1.5 text-xs font-medium text-white hover:bg-accent-hover">Add Target</button>
                        <button wire:click="$set('addingElrTargetToStageId', null)" class="rounded-lg bg-surface-2 px-3 py-1.5 text-xs font-medium text-muted hover:text-primary">Cancel</button>
                    </div>
                </div>
                @else
                <div class="border-t border-border px-6 py-2">
                    <button wire:click="$set('addingElrTargetToStageId', {{ $stage->id }})" class="text-xs font-medium text-accent hover:text-accent-hover">+ Add Target</button>
                </div>
                @endif
            </div>
            @endforeach

            {{-- Add stage form --}}
            <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4">
                <h3 class="mb-3 text-sm font-semibold text-secondary">Add Stage</h3>
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <label class="block text-xs text-muted mb-1">Stage Label</label>
                        <input type="text" wire:model="elrStageLabel" placeholder="Stage 1" class="w-full rounded-lg border border-border bg-app px-3 py-2 text-sm text-primary placeholder-muted focus:border-accent focus:outline-none" />
                    </div>
                    <div class="w-32">
                        <label class="block text-xs text-muted mb-1">Type</label>
                        <select wire:model="elrStageType" class="w-full rounded-lg border border-border bg-app px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none">
                            <option value="ladder">Ladder</option>
                            <option value="static">Static</option>
                        </select>
                    </div>
                    <button wire:click="addElrStage" class="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white hover:bg-accent-hover">
                        Add Stage
                    </button>
                </div>
            </div>
        </div>
        @endif

        <flux:separator />

        {{-- Squads summary --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-primary">Squads</h2>
                <flux:button href="{{ route('org.matches.squadding', [$organization, $match]) }}" variant="primary" size="sm" class="!bg-accent hover:!bg-accent-hover">
                    Manage Squadding
                </flux:button>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-border bg-surface-2/30 p-3 text-center">
                    <p class="text-2xl font-bold text-primary">{{ $squads->count() }}</p>
                    <p class="text-xs text-muted">Squads</p>
                </div>
                <div class="rounded-lg border border-border bg-surface-2/30 p-3 text-center">
                    <p class="text-2xl font-bold text-primary">{{ $squads->sum(fn($s) => $s->shooters->count()) }}</p>
                    <p class="text-xs text-muted">Shooters</p>
                </div>
                <div class="rounded-lg border border-border bg-surface-2/30 p-3 text-center">
                    <p class="text-2xl font-bold text-primary">{{ $match->registrations()->where('payment_status', 'confirmed')->count() }}</p>
                    <p class="text-xs text-muted">Confirmed</p>
                </div>
                <div class="rounded-lg border border-border bg-surface-2/30 p-3 text-center">
                    <p class="text-2xl font-bold text-primary">{{ $match->registrations()->count() }}</p>
                    <p class="text-xs text-muted">Registrations</p>
                </div>
            </div>
    @endif
</div>
