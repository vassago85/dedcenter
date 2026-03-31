<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
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
    public string $entry_fee = '';
    public string $scoring_type = 'standard';
    public bool $side_bet_enabled = false;
    public int $concurrent_relays = 2;

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
            if ($match->created_by !== auth()->id() && !auth()->user()->isAdmin()) {
                abort(403, 'Only the match creator can edit this match.');
            }

            $this->match = $match;
            $this->name = $match->name;
            $this->date = $match->date?->format('Y-m-d') ?? '';
            $this->location = $match->location ?? '';
            $this->notes = $match->notes ?? '';
            $this->entry_fee = $match->entry_fee ? (string) $match->entry_fee : '';
            $this->scoring_type = $match->scoring_type ?? 'standard';
            $this->side_bet_enabled = (bool) $match->side_bet_enabled;
            $this->concurrent_relays = $match->concurrent_relays ?? 2;
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
            'entry_fee' => 'nullable|numeric|min:0',
            'scoring_type' => 'required|in:standard,prs,elr',
        ]);

        $validated['entry_fee'] = $this->entry_fee !== '' ? (float) $this->entry_fee : null;
        $validated['side_bet_enabled'] = $this->scoring_type === 'standard' && $this->side_bet_enabled;
        $validated['concurrent_relays'] = $this->scoring_type === 'standard' ? max(1, $this->concurrent_relays) : 1;

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
            $ts->update(['is_timed_stage' => (bool) $value]);
        }
    }

    public function setTiebreakerStage(int $targetSetId): void
    {
        $this->match->targetSets()->update(['is_tiebreaker' => false]);
        TargetSet::where('id', $targetSetId)->where('match_id', $this->match->id)->update(['is_tiebreaker' => true]);
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

    public function startMatch(): void { $this->match->update(['status' => MatchStatus::Active]); Flux::toast('Match started!', variant: 'success'); }
    public function completeMatch(): void { $this->match->update(['status' => MatchStatus::Completed]); Flux::toast('Match completed.', variant: 'success'); }
    public function reopenMatch(): void { $this->match->update(['status' => MatchStatus::Active]); Flux::toast('Match reopened.', variant: 'success'); }

    public function with(): array
    {
        $data = ['divisions' => collect(), 'categories' => collect(), 'qrCodeSvg' => null];
        if ($this->match) {
            $data['targetSets'] = $this->match->targetSets()->with('gongs')->orderBy('sort_order')->get();
            $data['squads'] = $this->match->squads()->with(['shooters.division', 'shooters.categories'])->orderBy('sort_order')->get();
            $data['divisions'] = $this->match->divisions()->orderBy('sort_order')->get();
            $data['categories'] = $this->match->categories()->orderBy('sort_order')->get();

            if (in_array($this->match->status, [MatchStatus::Active, MatchStatus::Completed])) {
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
                <div class="rounded-lg border border-border bg-surface-2/30 p-4 space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="side_bet_enabled"
                               class="rounded border-border bg-surface-2 text-accent focus:ring-red-500 focus:ring-offset-0 h-5 w-5" />
                        <div>
                            <span class="text-sm font-medium text-primary">Enable Side Bet</span>
                            <p class="text-xs text-muted">Rank by smallest gong hits with furthest-distance tiebreaker. Winner is whoever hits the most small gongs.</p>
                        </div>
                    </label>
                    <div class="border-t border-border pt-4">
                        <div class="flex items-center gap-4">
                            <div class="w-32">
                                <label class="block text-sm font-medium text-secondary mb-1">Concurrent Relays</label>
                                <input type="number" wire:model="concurrent_relays" min="1" max="10"
                                       class="w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-primary text-center focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                            </div>
                            <p class="text-xs text-muted flex-1">How many relays shoot at the same time. Shared-rifle partners will be placed in different concurrent groups.</p>
                        </div>
                    </div>
                </div>
            @endif
            <flux:textarea wire:model="notes" label="Notes" placeholder="Optional notes about this match..." rows="3" />
            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                    {{ $match ? 'Save Changes' : 'Create Match' }}
                </flux:button>
            </div>
        </div>
    </form>

    @if($match)
        <div class="rounded-xl border border-border bg-surface p-6">
            <h2 class="text-lg font-semibold text-primary mb-4">Match Controls</h2>
            <div class="flex flex-wrap gap-3">
                @if($match->status === MatchStatus::Draft)
                    <flux:button wire:click="startMatch" variant="primary" class="!bg-green-600 hover:!bg-green-700" wire:confirm="Start this match?">Start Match</flux:button>
                    <flux:button href="{{ route('org.matches.squadding', [$organization, $match]) }}" variant="ghost">Squadding</flux:button>
                @elseif($match->status === MatchStatus::Active)
                    <flux:button wire:click="completeMatch" variant="primary" class="!bg-blue-600 hover:!bg-blue-700" wire:confirm="Complete this match?">Complete Match</flux:button>
                    <flux:button href="{{ route('org.matches.squadding', [$organization, $match]) }}" variant="ghost">Squadding</flux:button>
                    <flux:button href="{{ route('score') }}" target="_blank" variant="ghost">Open Scoring</flux:button>
                    <flux:button href="{{ route('scoreboard', $match) }}" target="_blank" variant="ghost">View Scoreboard</flux:button>
                    <flux:button href="{{ route('org.matches.export.standings', [$organization, $match]) }}" variant="ghost">Download Standings</flux:button>
                    <flux:button href="{{ route('org.matches.export.detailed', [$organization, $match]) }}" variant="ghost">Download Full Results</flux:button>
                @elseif($match->status === MatchStatus::Completed)
                    <flux:button wire:click="reopenMatch" variant="ghost" wire:confirm="Reopen this match?">Reopen Match</flux:button>
                    <flux:button href="{{ route('org.matches.export.standings', [$organization, $match]) }}" variant="ghost">Download Standings</flux:button>
                    <flux:button href="{{ route('org.matches.export.detailed', [$organization, $match]) }}" variant="ghost">Download Full Results</flux:button>
                @endif
            </div>

            @if($qrCodeSvg)
                <div class="mt-4 border-t border-border pt-4">
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
                                   wire:change="updateTargetSet({{ $ts->id }}, 'is_timed_stage', $event.target.checked ? '1' : '0')"
                                   class="rounded border-border bg-surface-2 text-amber-600 focus:ring-amber-500" />
                            <span class="text-muted font-medium">Timed stage</span>
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

        {{-- Squads --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-primary">Squads</h2>
            @foreach($squads as $squad)
                <div class="rounded-xl border border-border bg-surface overflow-hidden" wire:key="squad-{{ $squad->id }}">
                    <div class="flex items-center justify-between border-b border-border px-6 py-3">
                        <span class="font-medium text-primary">{{ $squad->name }}</span>
                        <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent" wire:click="deleteSquad({{ $squad->id }})" wire:confirm="Delete this squad?">Delete</flux:button>
                    </div>
                    <div class="p-4 space-y-3">
                        @if($squad->shooters->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead><tr class="text-left text-muted border-b border-border/50"><th class="px-3 py-2 font-medium">Name</th><th class="px-3 py-2 font-medium">Bib #</th>@if($divisions->isNotEmpty())<th class="px-3 py-2 font-medium">Division</th>@endif @if($categories->isNotEmpty())<th class="px-3 py-2 font-medium">Categories</th>@endif<th class="px-3 py-2 font-medium"></th></tr></thead>
                                    <tbody class="divide-y divide-border/50">
                                        @foreach($squad->shooters->sortBy('sort_order') as $shooter)
                                            <tr wire:key="shooter-{{ $shooter->id }}" class="{{ $shooter->isWithdrawn() ? 'opacity-40' : '' }}">
                                                <td class="px-3 py-2 {{ $shooter->isWithdrawn() ? 'line-through text-muted' : 'text-secondary' }}">
                                                    {{ $shooter->name }}
                                                    @if($shooter->isWithdrawn())
                                                        <span class="ml-1 text-[10px] font-medium uppercase text-amber-400 no-underline inline-block" style="text-decoration:none;">DNS</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-secondary">{{ $shooter->bib_number ?? '—' }}</td>
                                                @if($divisions->isNotEmpty())
                                                    <td class="px-3 py-2">
                                                        @if($shooter->isActive())
                                                        <select class="rounded border border-border bg-surface-2 px-2 py-1 text-xs text-primary focus:border-red-500"
                                                                wire:change="updateShooterDivision({{ $shooter->id }}, $event.target.value)">
                                                            <option value="" {{ !$shooter->match_division_id ? 'selected' : '' }}>—</option>
                                                            @foreach($divisions as $d)
                                                                <option value="{{ $d->id }}" {{ $shooter->match_division_id == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @else
                                                            <span class="text-xs text-muted">{{ $shooter->division?->name ?? '—' }}</span>
                                                        @endif
                                                    </td>
                                                @endif
                                                @if($categories->isNotEmpty())
                                                    <td class="px-3 py-2">
                                                        @if($shooter->isActive())
                                                        <div class="flex flex-wrap gap-1" x-data="{ cats: {{ json_encode($shooter->categories->pluck('id')->toArray()) }} }">
                                                            @foreach($categories as $cat)
                                                                <label class="inline-flex items-center gap-0.5 cursor-pointer">
                                                                    <input type="checkbox" value="{{ $cat->id }}"
                                                                           class="rounded border-border bg-surface-2 text-accent focus:ring-red-500 focus:ring-offset-0 h-3 w-3"
                                                                           {{ $shooter->categories->contains('id', $cat->id) ? 'checked' : '' }}
                                                                           x-on:change="
                                                                               let id = {{ $cat->id }};
                                                                               if ($event.target.checked) { cats.push(id); } else { cats = cats.filter(c => c !== id); }
                                                                               $wire.updateShooterCategories({{ $shooter->id }}, [...cats]);
                                                                           " />
                                                                    <span class="text-[10px] text-muted">{{ $cat->name }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                @endif
                                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                                    <div class="flex items-center justify-end gap-1" x-data="{ showMenu: false }">
                                                        @if($shooter->isActive())
                                                            <button wire:click="toggleShooterStatus({{ $shooter->id }})"
                                                                    class="rounded px-1.5 py-0.5 text-[10px] font-medium text-amber-400 hover:bg-amber-400/10 transition-colors"
                                                                    title="Mark as no-show / withdrawn">DNS</button>
                                                        @else
                                                            <button wire:click="toggleShooterStatus({{ $shooter->id }})"
                                                                    class="rounded px-1.5 py-0.5 text-[10px] font-medium text-green-400 hover:bg-green-400/10 transition-colors"
                                                                    title="Reactivate shooter">Activate</button>
                                                        @endif
                                                        @if($this->match->squads->count() > 1)
                                                            <div class="relative" @click.away="showMenu = false">
                                                                <button @click="showMenu = !showMenu" class="rounded px-1 py-0.5 text-xs text-muted hover:text-secondary transition-colors" title="Move to squad">&#8644;</button>
                                                                <div x-show="showMenu" x-transition class="absolute right-0 z-10 mt-1 w-40 rounded-lg border border-border bg-surface-2 py-1 shadow-lg">
                                                                    @foreach($this->match->squads->where('id', '!=', $squad->id) as $otherSquad)
                                                                        <button wire:click="moveShooter({{ $shooter->id }}, {{ $otherSquad->id }})" @click="showMenu = false"
                                                                                class="block w-full px-3 py-1.5 text-left text-xs text-secondary hover:bg-surface hover:text-white transition-colors">{{ $otherSquad->name }}</button>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <button wire:click="deleteShooter({{ $shooter->id }})" wire:confirm="Permanently delete {{ $shooter->name }}? This removes all their scores."
                                                                class="rounded px-1 py-0.5 text-xs text-accent/60 hover:text-accent transition-colors" title="Delete permanently">&times;</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-muted px-3">No shooters yet.</p>
                        @endif
                        @if($addingShooterToSquadId === $squad->id)
                            <div class="rounded-lg border border-border bg-surface-2/50 p-4 space-y-3">
                                <div class="grid grid-cols-2 gap-3 {{ $divisions->isNotEmpty() ? 'sm:grid-cols-3' : '' }}">
                                    <flux:input wire:model="shooterName" label="Name" placeholder="Shooter name" required />
                                    <flux:input wire:model="shooterBib" label="Bib #" placeholder="Optional" />
                                    @if($divisions->isNotEmpty())
                                        <div>
                                            <label class="block text-sm font-medium text-secondary mb-1">Division</label>
                                            <select wire:model="shooterDivision" class="w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500">
                                                <option value="">No Division</option>
                                                @foreach($divisions as $d)
                                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                                @if($categories->isNotEmpty())
                                    <div class="col-span-full">
                                        <label class="block text-sm font-medium text-secondary mb-1">Categories</label>
                                        <div class="flex flex-wrap gap-3">
                                            @foreach($categories as $cat)
                                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                                    <input type="checkbox" value="{{ $cat->id }}" wire:model="shooterCategories"
                                                           class="rounded border-border bg-surface-2 text-accent focus:ring-red-500 focus:ring-offset-0" />
                                                    <span class="text-sm text-secondary">{{ $cat->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div class="col-span-full flex gap-2">
                                    <flux:button wire:click="addShooter" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add Shooter</flux:button>
                                    <flux:button wire:click="$set('addingShooterToSquadId', null)" size="sm" variant="ghost">Cancel</flux:button>
                                </div>
                            </div>
                        @else
                            <div class="px-3"><flux:button size="sm" variant="ghost" wire:click="startAddShooter({{ $squad->id }})">+ Add Shooter</flux:button></div>
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4 space-y-3">
                <h3 class="text-sm font-medium text-secondary">Add Squad</h3>
                <div class="flex gap-3">
                    <div class="flex-1"><flux:input wire:model="squadName" placeholder="e.g. Squad A" /></div>
                    <flux:button wire:click="addSquad" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover self-end">Add Squad</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
