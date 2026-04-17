<?php

use App\Models\ShootingMatch;
use App\Models\Season;
use App\Models\MatchDivision;
use App\Models\MatchCategory;
use App\Models\TargetSet;
use App\Models\Gong;
use App\Models\StagePosition;
use App\Models\StageShotSequence;
use App\Models\Squad;
use App\Models\Shooter;
use App\Services\RoyalFlushEquipmentImportService;
use App\Enums\MatchStatus;
use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Output\QRMarkupSVG;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')]
    class extends Component {
    use WithFileUploads;

    public ?ShootingMatch $match = null;
    public $coverImage = null;

    public string $name = '';
    public string $date = '';
    public string $location = '';
    public string $notes = '';
    public string $public_bio = '';
    public string $entry_fee = '';
    public string $scoring_type = 'standard';
    public bool $side_bet_enabled = false;
    public bool $royal_flush_enabled = false;
    public array $sideBetShooterIds = [];
    public bool $scores_published = true;

    // Custom Registration Fields
    public array $customFields = [];
    public string $cfLabel = '';
    public string $cfType = 'text';
    public string $cfOptions = '';
    public bool $cfRequired = false;
    public bool $cfShowScoreboard = false;
    public bool $cfShowResults = false;
    public ?int $editingCustomFieldId = null;
    public int $concurrent_relays = 2;
    public ?int $season_id = null;

    public string $tsDistance = '';
    public string $tsLabel = '';

    public string $gongNumber = '';
    public string $gongLabel = '';
    public string $gongMultiplier = '1.00';
    public string $gongDistance = '';
    public string $gongSize = '';
    public string $gongSizeMm = '';
    public ?int $addingGongToTargetSetId = null;

    public string $squadName = '';

    public string $shooterName = '';
    public string $shooterBib = '';
    public ?int $addingShooterToSquadId = null;
    public ?int $shooterDivision = null;

    public string $divisionName = '';
    public string $categoryName = '';
    public array $shooterCategories = [];

    public string $equipmentImportPaste = '';

    public bool $equipmentImportFreeEntry = true;

    public bool $equipmentImportAddShooters = true;

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

    public function mount(?ShootingMatch $match = null): void
    {
        if ($match && $match->exists) {
            $this->match = $match;
            $this->name = $match->name;
            $this->date = $match->date?->format('Y-m-d') ?? '';
            $this->location = $match->location ?? '';
            $this->notes = $match->notes ?? '';
            $this->public_bio = $match->public_bio ?? '';
            $this->entry_fee = $match->entry_fee ? (string) $match->entry_fee : '';
            $this->scoring_type = $match->scoring_type ?? 'standard';
            $this->side_bet_enabled = (bool) $match->side_bet_enabled;
            $this->royal_flush_enabled = (bool) $match->royal_flush_enabled;
            $this->sideBetShooterIds = $match->sideBetShooters()->pluck('shooters.id')->map(fn ($id) => (int) $id)->toArray();
            $this->concurrent_relays = $match->concurrent_relays ?? 2;
            $this->scores_published = (bool) ($match->scores_published ?? true);
            $this->season_id = $match->season_id;
            $this->loadCustomFields();
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
            'coverImage' => 'nullable|image|max:4096',
        ]);

        $validated['entry_fee'] = $this->entry_fee !== '' ? (float) $this->entry_fee : null;
        $orgIsRf = $this->match?->organization?->isRoyalFlushOrg() ?? false;
        $validated['royal_flush_enabled'] = $orgIsRf && $this->scoring_type === 'standard' && $this->royal_flush_enabled;
        $validated['side_bet_enabled'] = $validated['royal_flush_enabled'] && $this->side_bet_enabled;
        $validated['concurrent_relays'] = $this->scoring_type === 'standard' ? max(1, $this->concurrent_relays) : 1;
        $validated['scores_published'] = $this->scores_published;

        $org = $this->match?->organization;
        if ($org && ! $org->season_standings_enabled) {
            $validated['season_id'] = null;
        } else {
            $validated['season_id'] = $this->season_id ?: null;
        }

        if ($this->coverImage) {
            $matchId = $this->match?->id ?? 'new';
            if ($this->match?->image_url) {
                Storage::disk('public')->delete($this->match->image_url);
            }
            $validated['image_url'] = $this->coverImage->store("match-covers/{$matchId}", 'public');
            $this->coverImage = null;
        }

        if ($this->match) {
            $this->match->update($validated);
            Flux::toast('Match updated.', variant: 'success');
        } else {
            $this->match = ShootingMatch::create([
                ...$validated,
                'created_by' => auth()->id(),
                'status' => MatchStatus::Draft,
            ]);

            if ($this->match->image_url && str_contains($this->match->image_url, 'match-covers/new/')) {
                $newPath = str_replace('match-covers/new/', "match-covers/{$this->match->id}/", $this->match->image_url);
                Storage::disk('public')->move($this->match->image_url, $newPath);
                $this->match->update(['image_url' => $newPath]);
            }

            Flux::toast('Match created.', variant: 'success');
            $this->redirect(route('admin.matches.edit', $this->match), navigate: true);
        }

        if (!$validated['side_bet_enabled']) {
            $this->match->sideBetShooters()->detach();
            $this->sideBetShooterIds = [];
        }
    }

    public function removeCoverImage(): void
    {
        if ($this->match?->image_url) {
            Storage::disk('public')->delete($this->match->image_url);
            $this->match->update(['image_url' => null]);
            Flux::toast('Cover image removed.', variant: 'success');
        }
    }

    public function saveSideBetParticipants(): void
    {
        if (! $this->match || ! $this->match->side_bet_enabled) {
            return;
        }

        if (in_array($this->match->status, [MatchStatus::Active, MatchStatus::Completed])) {
            Flux::toast('Buy-in is locked once scoring starts.', variant: 'danger');
            return;
        }

        $this->match->sideBetShooters()->sync($this->sideBetShooterIds);
        Flux::toast('Side bet participants saved.', variant: 'success');
    }

    // ── Custom Registration Fields ──

    private function loadCustomFields(): void
    {
        if (! $this->match) return;
        $this->customFields = $this->match->customFields()->orderBy('sort_order')->get()->toArray();
    }

    public function saveCustomField(): void
    {
        if (! $this->match) return;

        $this->validate([
            'cfLabel' => 'required|string|max:255',
            'cfType' => 'required|in:text,number,select,checkbox',
            'cfOptions' => $this->cfType === 'select' ? 'required|string' : 'nullable|string',
        ]);

        $options = null;
        if ($this->cfType === 'select' && $this->cfOptions) {
            $options = array_map('trim', explode(',', $this->cfOptions));
            $options = array_filter($options, fn ($o) => $o !== '');
            $options = array_values($options);
        }

        $data = [
            'label' => $this->cfLabel,
            'type' => $this->cfType,
            'options' => $options,
            'is_required' => $this->cfRequired,
            'show_on_scoreboard' => $this->cfShowScoreboard,
            'show_on_results' => $this->cfShowResults,
            'sort_order' => $this->editingCustomFieldId
                ? \App\Models\MatchCustomField::find($this->editingCustomFieldId)?->sort_order ?? 0
                : ($this->match->customFields()->max('sort_order') ?? 0) + 1,
        ];

        if ($this->editingCustomFieldId) {
            $field = $this->match->customFields()->findOrFail($this->editingCustomFieldId);
            $field->update($data);
            Flux::toast('Custom field updated.', variant: 'success');
        } else {
            $this->match->customFields()->create($data);
            Flux::toast('Custom field added.', variant: 'success');
        }

        $this->resetCustomFieldForm();
        $this->loadCustomFields();
    }

    public function editCustomField(int $id): void
    {
        $field = $this->match->customFields()->findOrFail($id);
        $this->editingCustomFieldId = $field->id;
        $this->cfLabel = $field->label;
        $this->cfType = $field->type;
        $this->cfOptions = is_array($field->options) ? implode(', ', $field->options) : '';
        $this->cfRequired = $field->is_required;
        $this->cfShowScoreboard = $field->show_on_scoreboard;
        $this->cfShowResults = $field->show_on_results;
    }

    public function deleteCustomField(int $id): void
    {
        $this->match->customFields()->findOrFail($id)->delete();
        $this->loadCustomFields();
        Flux::toast('Custom field removed.', variant: 'success');
    }

    public function cancelCustomFieldEdit(): void
    {
        $this->resetCustomFieldForm();
    }

    private function resetCustomFieldForm(): void
    {
        $this->editingCustomFieldId = null;
        $this->cfLabel = '';
        $this->cfType = 'text';
        $this->cfOptions = '';
        $this->cfRequired = false;
        $this->cfShowScoreboard = false;
        $this->cfShowResults = false;
    }

    // ── Target Sets ──

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
        } elseif ($field === 'total_shots') {
            $ts->update(['total_shots' => max(0, (int) $value)]);
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

        $gongMap = [];
        foreach ($source->gongs as $gong) {
            $newGong = $clone->gongs()->create([
                'number' => $gong->number, 'label' => $gong->label, 'multiplier' => $gong->multiplier,
                'distance_meters' => $gong->distance_meters, 'target_size' => $gong->target_size, 'target_size_mm' => $gong->target_size_mm,
            ]);
            $gongMap[$gong->id] = $newGong->id;
        }
        $posMap = [];
        foreach ($source->positions as $pos) {
            $newPos = $clone->positions()->create(['name' => $pos->name, 'sort_order' => $pos->sort_order]);
            $posMap[$pos->id] = $newPos->id;
        }
        foreach ($source->shotSequence as $seq) {
            if (isset($posMap[$seq->position_id]) && isset($gongMap[$seq->gong_id])) {
                $clone->shotSequence()->create(['shot_number' => $seq->shot_number, 'position_id' => $posMap[$seq->position_id], 'gong_id' => $gongMap[$seq->gong_id]]);
            }
        }

        Flux::toast('Stage cloned.', variant: 'success');
    }

    public function deleteTargetSet(int $id): void
    {
        TargetSet::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Target set deleted.', variant: 'success');
    }

    // ── Gongs ──

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
            Gong::create([
                'target_set_id' => $targetSetId,
                'number' => $maxNumber + $s['number'],
                'label' => $s['label'],
                'multiplier' => $s['multiplier'],
            ]);
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
        $seqCount = StageShotSequence::where('stage_id', $targetSetId)->count();
        $gongCount = Gong::where('target_set_id', $targetSetId)->count();

        if ($seqCount > 0 || $gongCount > 0) {
            $count = $seqCount > 0 ? $seqCount : $gongCount;
            TargetSet::where('id', $targetSetId)->update(['total_shots' => $count]);
        }
    }

    public function startAddGong(int $targetSetId): void
    {
        $this->addingGongToTargetSetId = $targetSetId;
        $this->reset('gongNumber', 'gongLabel', 'gongMultiplier', 'gongDistance', 'gongSize', 'gongSizeMm');
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
            'target_size_mm' => $isPrs && $this->gongSizeMm ? (float) $this->gongSizeMm : null,
        ]);

        if ($isPrs) {
            $this->syncPrsTargetCount($this->addingGongToTargetSetId);
        }

        $this->addingGongToTargetSetId = null;
        $this->reset('gongNumber', 'gongLabel', 'gongMultiplier', 'gongDistance', 'gongSize', 'gongSizeMm');
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
        } elseif ($field === 'target_size_mm') {
            $gong->update(['target_size_mm' => $value !== '' ? max(0.01, (float) $value) : null]);
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

    // ── PRS Positions & Shot Sequence ──

    public function addPosition(int $stageId): void
    {
        $ts = TargetSet::where('id', $stageId)->where('match_id', $this->match->id)->firstOrFail();
        $maxSort = $ts->positions()->max('sort_order') ?? 0;
        $ts->positions()->create(['name' => 'Position ' . ($maxSort + 1), 'sort_order' => $maxSort + 1]);
        Flux::toast('Position added.', variant: 'success');
    }

    public function updatePosition(int $positionId, string $name): void
    {
        StagePosition::findOrFail($positionId)->update(['name' => $name]);
    }

    public function deletePosition(int $positionId): void
    {
        $pos = StagePosition::findOrFail($positionId);
        $stageId = $pos->stage_id;
        $pos->delete();
        $this->resequenceShotNumbers($stageId);
        $this->syncPrsTargetCount($stageId);
        Flux::toast('Position removed.', variant: 'success');
    }

    public function toggleShotSequence(int $stageId, int $positionId, int $gongId): void
    {
        $existing = StageShotSequence::where('stage_id', $stageId)->where('position_id', $positionId)->where('gong_id', $gongId)->first();
        if ($existing) {
            $existing->delete();
            $this->resequenceShotNumbers($stageId);
        } else {
            $maxShot = StageShotSequence::where('stage_id', $stageId)->max('shot_number') ?? 0;
            StageShotSequence::create(['stage_id' => $stageId, 'shot_number' => $maxShot + 1, 'position_id' => $positionId, 'gong_id' => $gongId]);
        }
        $this->syncPrsTargetCount($stageId);
    }

    public function reorderShotSequence(int $stageId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            StageShotSequence::where('id', $id)->where('stage_id', $stageId)->update(['shot_number' => $index + 1]);
        }
    }

    public function reorderShots(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            StageShotSequence::where('id', (int) $id)->update(['shot_number' => $index + 1]);
        }
    }

    public function autoFillSequence(int $stageId): void
    {
        $ts = TargetSet::where('id', $stageId)->where('match_id', $this->match->id)->firstOrFail();
        StageShotSequence::where('stage_id', $stageId)->delete();
        $positions = $ts->positions()->orderBy('sort_order')->get();
        $gongs = $ts->gongs()->orderBy('number')->get();
        $n = 1;
        foreach ($positions as $pos) { foreach ($gongs as $gong) { StageShotSequence::create(['stage_id' => $stageId, 'shot_number' => $n++, 'position_id' => $pos->id, 'gong_id' => $gong->id]); } }
        $this->syncPrsTargetCount($stageId);
        Flux::toast('Shot sequence generated.', variant: 'success');
    }

    public function clearSequence(int $stageId): void
    {
        StageShotSequence::where('stage_id', $stageId)->delete();
        $this->syncPrsTargetCount($stageId);
        Flux::toast('Shot sequence cleared.', variant: 'success');
    }

    private function resequenceShotNumbers(int $stageId): void
    {
        $rows = StageShotSequence::where('stage_id', $stageId)->orderBy('shot_number')->get();
        foreach ($rows->values() as $i => $row) { if ($row->shot_number !== $i + 1) { $row->update(['shot_number' => $i + 1]); } }
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

    // ── Squads ──

    public function addSquad(): void
    {
        $this->validate(['squadName' => 'required|string|max:255']);

        $maxSort = $this->match->squads()->max('sort_order') ?? 0;

        $this->match->squads()->create([
            'name' => $this->squadName,
            'sort_order' => $maxSort + 1,
        ]);

        $this->reset('squadName');
        Flux::toast('Squad added.', variant: 'success');
    }

    public function deleteSquad(int $id): void
    {
        Squad::where('id', $id)->where('match_id', $this->match->id)->delete();
        Flux::toast('Squad deleted.', variant: 'success');
    }

    // ── Shooters ──

    public function startAddShooter(int $squadId): void
    {
        $this->addingShooterToSquadId = $squadId;
        $this->reset('shooterName', 'shooterBib');
        $this->shooterDivision = null;
        $this->shooterCategories = [];
    }

    public function addShooter(): void
    {
        $this->validate([
            'shooterName' => 'required|string|max:255',
            'shooterBib' => 'nullable|string|max:50',
        ]);

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

    public function importEquipmentRegistrations(): void
    {
        if (! $this->match) {
            Flux::toast('Save the match first.', variant: 'danger');

            return;
        }

        $this->validate([
            'equipmentImportPaste' => 'required|string|min:20',
        ]);

        try {
            $svc = app(RoyalFlushEquipmentImportService::class);
            $r = $svc->import(
                $this->match->fresh(),
                $this->equipmentImportPaste,
                $this->equipmentImportFreeEntry,
                $this->equipmentImportAddShooters,
            );
            $this->equipmentImportPaste = '';
            $summary = "{$r['created_users']} new users, {$r['created_registrations']} new registrations, {$r['updated_registrations']} updated, {$r['shooters_added']} shooters added to Default.";
            if ($r['skipped_rows'] > 0) {
                $summary .= " {$r['skipped_rows']} rows skipped.";
            }
            Flux::toast($summary, variant: 'success');
            if ($r['warnings'] !== []) {
                $msg = implode(' ', $r['warnings']);
                Flux::toast(strlen($msg) > 240 ? substr($msg, 0, 237).'…' : $msg, variant: 'warning');
            }
        } catch (\Throwable $e) {
            Flux::toast('Import failed: '.$e->getMessage(), variant: 'danger');
        }
    }

    public function toggleShooterStatus(int $id): void
    {
        $shooter = Shooter::findOrFail($id);
        if ($shooter->isDq()) {
            Flux::toast("{$shooter->name} is disqualified — revoke the DQ first.", variant: 'danger');
            return;
        }
        $shooter->update([
            'status' => $shooter->isActive() ? 'withdrawn' : 'active',
        ]);

        $label = $shooter->isActive() ? 'reactivated' : 'withdrawn';
        Flux::toast("Shooter {$label}.", variant: 'success');
    }

    public function deleteShooter(int $id): void
    {
        Shooter::destroy($id);
        Flux::toast('Shooter removed permanently.', variant: 'success');
    }

    public function moveShooter(int $shooterId, int $targetSquadId): void
    {
        $shooter = Shooter::findOrFail($shooterId);
        $targetSquad = $this->match->squads()->findOrFail($targetSquadId);

        $maxSort = Shooter::where('squad_id', $targetSquadId)->max('sort_order') ?? 0;
        $shooter->update([
            'squad_id' => $targetSquadId,
            'sort_order' => $maxSort + 1,
        ]);

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

    // ── Match Controls ──

    public function transitionStatus(string $target): void
    {
        $targetStatus = MatchStatus::from($target);
        if (! $this->match->status->canTransitionTo($targetStatus)) {
            Flux::toast('Invalid status transition.', variant: 'danger');
            return;
        }
        $oldStatus = $this->match->status;
        $this->match->update(['status' => $targetStatus]);

        try {
            app(\App\Services\NotificationService::class)->onStatusChange($this->match, $oldStatus, $targetStatus);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Status notification dispatch failed', ['error' => $e->getMessage()]);
        }

        if ($targetStatus === MatchStatus::Completed) {
            try {
                if ($this->match->isPrs()) {
                    \App\Services\AchievementService::evaluateMatchCompletion($this->match);
                }
                if ($this->match->royal_flush_enabled) {
                    \App\Services\AchievementService::evaluateRoyalFlushCompletion($this->match);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Achievement evaluation failed', ['error' => $e->getMessage()]);
            }
        }

        Flux::toast("Match status changed to {$targetStatus->label()}.", variant: 'success');
    }

    public function reopenMatch(): void
    {
        $this->match->update(['status' => MatchStatus::Active]);
        Flux::toast('Match reopened.', variant: 'success');
    }

    public function toggleScoresPublished(): void
    {
        $this->scores_published = ! $this->scores_published;
        if ($this->match) {
            $this->match->update(['scores_published' => $this->scores_published]);
            if ($this->scores_published && $this->match->status === \App\Enums\MatchStatus::Completed) {
                \App\Jobs\SendPostMatchNotifications::dispatch($this->match)->delay(now()->addHour());
            }
            Flux::toast($this->scores_published ? 'Scores are now live.' : 'Scores hidden from public.', variant: 'success');
        }
    }

    public function with(): array
    {
        $data = ['divisions' => collect(), 'categories' => collect(), 'qrCodeSvg' => null];

        if ($this->match) {
            $data['targetSets'] = $this->match->targetSets()
                ->with(['gongs', 'positions', 'shotSequence'])
                ->orderBy('sort_order')
                ->get();

            $data['squads'] = $this->match->squads()
                ->with(['shooters.division', 'shooters.categories'])
                ->orderBy('sort_order')
                ->get();

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

<div class="space-y-8 max-w-4xl" x-data="{ tab: 'info' }">
    <div class="flex items-center gap-4">
        <flux:button href="{{ route('admin.matches.index') }}" variant="ghost" size="sm">
            <x-icon name="chevron-left" class="mr-1 h-4 w-4" />
            Back
        </flux:button>
        <div>
            <flux:heading size="xl">{{ $match ? 'Edit Match' : 'New Match' }}</flux:heading>
            @if($match)
                <p class="mt-1 text-sm text-muted">
                    Status: <flux:badge size="sm" color="{{ $match->status->color() }}">{{ $match->status->label() }}</flux:badge>
                </p>
            @endif
        </div>
    </div>

    @if($match)
    <div class="flex gap-2 border-b border-border pb-3">
        <button type="button" @click="tab = 'info'" :class="tab === 'info' ? 'bg-accent text-white' : 'bg-surface-2 text-secondary hover:text-primary'" class="rounded-lg px-4 py-2 text-sm font-medium transition-colors">Match Info</button>
        <button type="button" @click="tab = 'stages'" :class="tab === 'stages' ? 'bg-accent text-white' : 'bg-surface-2 text-secondary hover:text-primary'" class="rounded-lg px-4 py-2 text-sm font-medium transition-colors">Stages</button>
        <button type="button" @click="tab = 'config'" :class="tab === 'config' ? 'bg-accent text-white' : 'bg-surface-2 text-secondary hover:text-primary'" class="rounded-lg px-4 py-2 text-sm font-medium transition-colors">Configuration</button>
    </div>
    @endif

    <div x-show="tab === 'info'">
    {{-- Match form --}}
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
                            Relay: Gong multipliers, relay-style scoring.
                        @endif
                    </p>
                </div>
            </div>

            @if($scoring_type === 'standard')
                <div class="rounded-lg border border-border bg-surface-2/30 p-4 space-y-4">
                    @if($match?->organization?->isRoyalFlushOrg())
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="royal_flush_enabled"
                                   class="rounded border-slate-600 bg-surface-2 text-amber-500 focus:ring-amber-500 focus:ring-offset-0 h-5 w-5" />
                            <div>
                                <span class="text-sm font-medium text-primary">Enable Royal Flush</span>
                                <p class="text-xs text-muted">Track when a shooter hits all targets at a distance. Awarded per distance for prize giving.</p>
                            </div>
                        </label>

                        @if($royal_flush_enabled)
                            <div class="ml-8 space-y-3 border-l-2 border-amber-600/30 pl-4">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="side_bet_enabled"
                                           class="rounded border-slate-600 bg-surface-2 text-accent focus:ring-red-500 focus:ring-offset-0 h-5 w-5" />
                                    <div>
                                        <span class="text-sm font-medium text-primary">Enable Side Bet</span>
                                        <p class="text-xs text-muted">Rank opted-in shooters by smallest gong hits. Only available on Royal Flush matches.</p>
                                    </div>
                                </label>

                                @if($side_bet_enabled && $match)
                                    <p class="text-xs text-muted">Participants are managed in the <strong>Side Bet Buy-In</strong> section below. Buy-in is locked once scoring starts.</p>
                                @endif
                            </div>
                        @endif
                    @endif

                    <div class="{{ $match?->organization?->isRoyalFlushOrg() ? 'border-t border-border pt-4' : '' }}">
                        <div class="flex items-center gap-4">
                            <div class="w-32">
                                <label class="block text-sm font-medium text-secondary mb-1">Concurrent Relays</label>
                                <input type="number" wire:model="concurrent_relays" min="1" max="10"
                                       class="w-full rounded-md border border-slate-600 bg-surface-2 px-3 py-2 text-sm text-primary text-center focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                            </div>
                            <p class="text-xs text-muted flex-1">How many relays shoot at the same time. Shared-rifle partners will be placed in different concurrent groups.</p>
                        </div>
                    </div>
                </div>
            @endif

            @php
                $seasonUi = ! $match?->organization_id || ($match->organization->season_standings_enabled ?? true);
            @endphp
            @if($seasonUi)
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">Season</label>
                    <select wire:model="season_id"
                            class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500">
                        <option value="">No season</option>
                        @foreach(\App\Models\Season::orderByDesc('year')->get() as $season)
                            <option value="{{ $season->id }}">{{ $season->name }} ({{ $season->year }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-muted">Optional — assign this match to a season for aggregate standings. Disabled for organizations that turned off season standings.</p>
                </div>
            @elseif($match?->organization)
                <p class="text-xs text-muted rounded-lg border border-border bg-surface-2/40 px-3 py-2">Season assignment is off for {{ $match->organization->name }}. Enable it under Organization settings if you need season aggregates.</p>
            @endif

            <flux:textarea wire:model="notes" label="Notes (internal)" placeholder="Staff-only notes..." rows="3" />
            <flux:textarea wire:model="public_bio" label="Public event bio" placeholder="Shown on public match pages..." rows="3" />

            <div>
                <label class="block text-sm font-medium text-primary mb-2">Cover image</label>
                <div class="flex items-start gap-4">
                    @if($match?->cover_image_url)
                        <div class="flex-shrink-0">
                            <img src="{{ $match->cover_image_url }}" alt="Cover" class="h-20 w-32 rounded-lg border border-border object-cover" />
                        </div>
                    @elseif($coverImage)
                        <div class="flex-shrink-0">
                            <img src="{{ $coverImage->temporaryUrl() }}" alt="Preview" class="h-20 w-32 rounded-lg border border-border object-cover" />
                        </div>
                    @endif
                    <div class="flex-1 space-y-2">
                        <input type="file" wire:model="coverImage" accept="image/*"
                               class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-2 file:px-4 file:py-2 file:text-sm file:font-medium file:text-secondary hover:file:bg-surface-2/80" />
                        @error('coverImage') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                        @if($match?->image_url)
                            <button type="button" wire:click="removeCoverImage" wire:confirm="Remove the cover image?" class="text-xs text-red-400 hover:underline">Remove image</button>
                        @endif
                        <p class="text-xs text-muted">Max 4 MB. Displayed on event cards and listings. Falls back to organization logo if not set.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                    {{ $match ? 'Save Changes' : 'Create Match' }}
                </flux:button>
            </div>
        </div>
    </form>
    </div>{{-- /tab:info --}}

    @if($match)
        <div x-show="tab === 'config'" x-cloak class="space-y-6">
        {{-- Status stepper --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-5">
            <h2 class="text-lg font-semibold text-primary">Match Lifecycle</h2>

            {{-- Stepper bar --}}
            @php
                $steps = \App\Enums\MatchStatus::cases();
                $currentOrd = $match->status->ordinal();
            @endphp
            <div class="flex items-center gap-1 overflow-x-auto pb-2">
                @foreach($steps as $step)
                    @php
                        $ord = $step->ordinal();
                        $isCurrent = $match->status === $step;
                        $isPast = $ord < $currentOrd;
                    @endphp
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

            {{-- Transition buttons --}}
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

            {{-- Quick links --}}
            <div class="flex flex-wrap gap-3 border-t border-border pt-4">
                <flux:button href="{{ route('admin.matches.squadding', $match) }}" variant="ghost">Manage Squadding</flux:button>

                @if(in_array($match->status, [MatchStatus::Active, MatchStatus::Completed, MatchStatus::SquaddingOpen]))
                    <flux:button href="{{ route('score') }}" target="_blank" variant="ghost">Open Scoring</flux:button>
                    <flux:button href="{{ route('scoreboard', $match) }}" target="_blank" variant="ghost">View Scoreboard</flux:button>
                @endif

                @if(in_array($match->status, [MatchStatus::Active, MatchStatus::Completed]))
                    <flux:button href="{{ route('admin.matches.export.standings', $match) }}" variant="ghost">Download Standings</flux:button>
                    <flux:button href="{{ route('admin.matches.export.detailed', $match) }}" variant="ghost">Download Full Results</flux:button>
                    @if($match->side_bet_enabled && $match->royal_flush_enabled)
                        <flux:button href="{{ route('admin.matches.side-bet-report', $match) }}" variant="ghost" class="!text-amber-400">Side Bet Report</flux:button>
                    @endif
                    <flux:button wire:click="toggleScoresPublished" variant="{{ $scores_published ? 'ghost' : 'primary' }}" class="{{ $scores_published ? '' : '!bg-amber-600 hover:!bg-amber-700' }}">
                        {{ $scores_published ? 'Hide Scores' : 'Publish Scores' }}
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
                                <input type="text" value="{{ $liveUrl }}" readonly class="flex-1 rounded-md border border-slate-600 bg-surface-2 px-3 py-1.5 text-xs text-secondary" />
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
                                   class="flex-1 rounded-md border border-slate-600 bg-surface-2 px-3 py-1.5 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500"
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
                <p class="text-[11px] text-muted">Divisions classify by equipment class. Single-select per shooter.</p>
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
                                   class="flex-1 rounded-md border border-slate-600 bg-surface-2 px-3 py-1.5 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500"
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
                <p class="text-[11px] text-muted">Categories classify by demographics. Multi-select per shooter (a score appears in all matching category leaderboards).</p>
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

        </div>{{-- /tab:config group 1 --}}

        <div x-show="tab === 'stages'" x-cloak class="space-y-6">

        {{-- Target Sets — Relay scoring --}}
        @if($scoring_type === 'standard')
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-primary">Target Sets</h2>

            @foreach($targetSets as $ts)
                <div class="rounded-xl border border-border bg-surface overflow-hidden" wire:key="ts-{{ $ts->id }}">
                    <div class="flex items-center justify-between border-b border-border px-6 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1">
                                <input type="number" value="{{ $ts->distance_meters }}" min="1"
                                       class="w-20 rounded-md border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                       wire:change="updateTargetSet({{ $ts->id }}, 'distance_meters', $event.target.value)" />
                                <span class="text-sm text-muted">m</span>
                            </div>
                            <div class="flex items-center gap-1" title="Distance multiplier applied to all gong scores">
                                <span class="text-xs text-muted">&times;</span>
                                <input type="number" value="{{ $ts->distance_multiplier ?? 1 }}" step="0.01" min="0.01"
                                       class="w-16 rounded-md border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-amber-400 text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
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
                                    <tbody class="divide-y divide-slate-700/50">
                                        @foreach($ts->gongs->sortBy('number') as $gong)
                                            <tr wire:key="gong-{{ $gong->id }}">
                                                <td class="px-3 py-1.5 text-muted font-mono">{{ $gong->number }}</td>
                                                <td class="px-3 py-1.5">
                                                    <input type="text" value="{{ $gong->label }}" placeholder="e.g. 2.5 MOA"
                                                           class="w-full rounded border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-primary placeholder-slate-500 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                                           wire:change="updateGong({{ $gong->id }}, 'label', $event.target.value)" />
                                                </td>
                                                <td class="px-3 py-1.5">
                                                    <div class="flex items-center gap-1">
                                                        <input type="number" value="{{ $gong->multiplier }}" step="0.01" min="0.01"
                                                               class="w-20 rounded border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-red-500 focus:ring-1 focus:ring-red-500"
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
                            <div class="rounded-lg border border-slate-600 bg-surface-2/50 p-4 space-y-3">
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
            <p class="text-xs text-muted">Set shots and par time per stage. Expand "Detailed Setup" to define individual targets, positions and shot sequences.</p>

            @foreach($targetSets as $ts)
                <div class="rounded-xl border {{ $ts->is_tiebreaker ? 'border-amber-500/50 ring-1 ring-amber-500/20' : 'border-border' }} bg-surface overflow-hidden" wire:key="ts-{{ $ts->id }}">
                    {{-- Stage header --}}
                    <div class="flex items-center justify-between border-b border-border px-6 py-3">
                        <div class="flex items-center gap-3">
                            <input type="text" value="{{ $ts->label }}" placeholder="Stage name"
                                   class="w-64 rounded-md border border-slate-600 bg-surface-2 px-3 py-1 text-sm font-semibold text-primary focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                   wire:change="updateTargetSet({{ $ts->id }}, 'label', $event.target.value)" />
                            <span class="text-xs text-muted">
                                {{ $ts->total_shots ?? 0 }} shots
                                @if($ts->par_time_seconds) / {{ rtrim(rtrim(number_format($ts->par_time_seconds, 2), '0'), '.') }}s @endif
                                @if($ts->gongs->isNotEmpty()) &middot; {{ $ts->gongs->count() }} targets @endif
                            </span>
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

                    {{-- Quick setup bar: shots, par time, timed --}}
                    <div class="flex flex-wrap items-center gap-4 border-b border-border/50 bg-surface/50 px-6 py-2">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-muted whitespace-nowrap">Shots:</label>
                            <input type="number" value="{{ $ts->total_shots }}" min="0" placeholder="e.g. 8"
                                   class="w-20 rounded-md border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                   wire:change="updateTargetSet({{ $ts->id }}, 'total_shots', $event.target.value)" />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-muted whitespace-nowrap">Par Time (s):</label>
                            <input type="number" value="{{ $ts->par_time_seconds }}" step="0.01" min="0" placeholder="e.g. 105"
                                   class="w-24 rounded-md border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                   wire:change="updateParTime({{ $ts->id }}, $event.target.value)" />
                        </div>
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" {{ $ts->is_timed_stage ? 'checked' : '' }}
                                   @if($this->match->scoring_type === 'prs') disabled title="PRS: timed is set automatically on the tiebreaker stage" @endif
                                   wire:change="updateTargetSet({{ $ts->id }}, 'is_timed_stage', $event.target.checked ? '1' : '0')"
                                   class="rounded border-slate-600 bg-surface-2 text-amber-600 focus:ring-amber-500 disabled:opacity-40 disabled:cursor-not-allowed" />
                            <span class="text-muted font-medium">Timed stage</span>
                            @if($this->match->scoring_type === 'prs')
                                <span class="text-[10px] text-slate-500">(auto: tiebreaker only)</span>
                            @endif
                        </label>
                    </div>

                    {{-- Collapsible detailed stage setup --}}
                    <div x-data="{ detailOpen: {{ ($ts->gongs->isNotEmpty() || $ts->positions->isNotEmpty()) ? 'true' : 'false' }} }">
                    <button type="button" @click="detailOpen = !detailOpen"
                            class="flex w-full items-center gap-2 px-6 py-2 text-xs font-medium text-secondary hover:text-primary transition-colors border-b border-border/50">
                        <x-icon name="chevron-right" class="h-3 w-3 text-muted transition-transform" x-bind:class="detailOpen && 'rotate-90'" />
                        Detailed Setup (Targets, Positions &amp; Shot Sequence)
                        @if($ts->gongs->isNotEmpty())
                            <span class="text-[10px] text-muted ml-1">{{ $ts->gongs->count() }} targets{{ $ts->positions->isNotEmpty() ? ', '.$ts->positions->count().' positions' : '' }}{{ $ts->shotSequence->isNotEmpty() ? ', '.$ts->shotSequence->count().' shots defined' : '' }}</span>
                        @endif
                    </button>

                    <div x-show="detailOpen" x-collapse>
                    {{-- Section A: Targets --}}
                    <div class="p-4 space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted">Targets</h4>
                        @if($ts->gongs->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-muted border-b border-border/50">
                                            <th class="px-3 py-2 font-medium w-12">#</th>
                                            <th class="px-3 py-2 font-medium">Name</th>
                                            <th class="px-3 py-2 font-medium w-28">Distance (m)</th>
                                            <th class="px-3 py-2 font-medium w-28">Size (mm)</th>
                                            <th class="px-3 py-2 font-medium w-24">Size (mrad)</th>
                                            <th class="px-3 py-2 font-medium w-28">Target Ref</th>
                                            <th class="px-3 py-2 font-medium w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-700/50">
                                        @foreach($ts->gongs->sortBy('number') as $gong)
                                            <tr wire:key="gong-{{ $gong->id }}">
                                                <td class="px-3 py-1.5 text-muted font-mono">{{ $gong->number }}</td>
                                                <td class="px-3 py-1.5">
                                                    <input type="text" value="{{ $gong->label }}" placeholder="e.g. T1"
                                                           class="w-full rounded border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-primary placeholder-slate-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                                           wire:change="updateGong({{ $gong->id }}, 'label', $event.target.value)" />
                                                </td>
                                                <td class="px-3 py-1.5">
                                                    <input type="number" value="{{ $gong->distance_meters }}" min="1" placeholder="m"
                                                           class="w-24 rounded border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                                           wire:change="updateGong({{ $gong->id }}, 'distance_meters', $event.target.value)" />
                                                </td>
                                                <td class="px-3 py-1.5">
                                                    <input type="number" value="{{ $gong->target_size_mm }}" step="0.01" min="0.01" placeholder="mm"
                                                           class="w-24 rounded border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                                           wire:change="updateGong({{ $gong->id }}, 'target_size_mm', $event.target.value)" />
                                                </td>
                                                <td class="px-3 py-1.5 text-center">
                                                    @if($gong->target_size_mm && $gong->distance_meters)
                                                        <span class="text-sm font-mono text-amber-400">{{ number_format($gong->target_size_mm / $gong->distance_meters, 2) }}</span>
                                                    @else
                                                        <span class="text-xs text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-1.5">
                                                    <input type="text" value="{{ $gong->target_size }}" placeholder="e.g. 2 MOA"
                                                           class="w-24 rounded border border-slate-600 bg-surface-2 px-2 py-1 text-sm text-primary text-center placeholder-slate-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
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
                            <div class="rounded-lg border border-slate-600 bg-surface-2/50 p-4 space-y-3">
                                <div class="grid grid-cols-5 gap-3">
                                    <flux:input wire:model="gongNumber" label="#" type="number" min="1" required />
                                    <flux:input wire:model="gongLabel" label="Name" placeholder="e.g. T1" />
                                    <flux:input wire:model="gongDistance" label="Distance (m)" type="number" min="1" placeholder="e.g. 400" />
                                    <flux:input wire:model="gongSize" label="Target Ref" placeholder="e.g. 2 MOA" />
                                    <flux:input wire:model="gongSizeMm" label="Size (mm)" type="number" step="0.01" min="0.01" placeholder="e.g. 200" />
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

                    {{-- Section B: Positions --}}
                    <div class="border-t border-border p-4 space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted">Positions</h4>
                        @if($ts->positions->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach($ts->positions as $pos)
                                    <div class="flex items-center gap-1 rounded-lg border border-border bg-surface-2/40 px-3 py-1.5" wire:key="pos-{{ $pos->id }}">
                                        <input type="text" value="{{ $pos->name }}" placeholder="Position name"
                                               class="w-32 bg-transparent border-0 px-0 py-0 text-sm text-primary placeholder-muted focus:ring-0"
                                               wire:change="updatePosition({{ $pos->id }}, $event.target.value)" />
                                        <button class="text-accent/60 hover:text-accent text-lg leading-none ml-1"
                                                wire:click="deletePosition({{ $pos->id }})"
                                                wire:confirm="Remove this position and its sequence entries?">&times;</button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-muted">No positions defined yet.</p>
                        @endif
                        <flux:button size="sm" variant="ghost" wire:click="addPosition({{ $ts->id }})">+ Add Position</flux:button>
                    </div>

                    {{-- Section C: Shot Sequence --}}
                    @if($ts->positions->isNotEmpty() && $ts->gongs->isNotEmpty())
                    <div class="border-t border-border p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-muted">
                                Shot Sequence <span class="ml-2 text-amber-400 font-mono normal-case">({{ $ts->shotSequence->count() }} shots)</span>
                            </h4>
                            <div class="flex gap-2">
                                <flux:button size="xs" variant="ghost" wire:click="autoFillSequence({{ $ts->id }})" wire:confirm="Generate all position × target combos? This replaces the current sequence.">Auto-fill</flux:button>
                                @if($ts->shotSequence->isNotEmpty())
                                    <flux:button size="xs" variant="ghost" class="!text-accent" wire:click="clearSequence({{ $ts->id }})" wire:confirm="Clear the entire shot sequence?">Clear</flux:button>
                                @endif
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="text-sm">
                                <thead>
                                    <tr class="text-left text-muted">
                                        <th class="px-3 py-2 font-medium text-xs"></th>
                                        @foreach($ts->gongs->sortBy('number') as $gong)
                                            <th class="px-3 py-2 font-medium text-xs text-center">{{ $gong->label ?: 'T'.$gong->number }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ts->positions as $pos)
                                        <tr wire:key="matrix-{{ $pos->id }}">
                                            <td class="px-3 py-1.5 text-xs font-medium text-secondary whitespace-nowrap">{{ $pos->name }}</td>
                                            @foreach($ts->gongs->sortBy('number') as $gong)
                                                @php $isChecked = $ts->shotSequence->contains(fn ($s) => $s->position_id === $pos->id && $s->gong_id === $gong->id); @endphp
                                                <td class="px-3 py-1.5 text-center">
                                                    <input type="checkbox" {{ $isChecked ? 'checked' : '' }}
                                                           wire:click="toggleShotSequence({{ $ts->id }}, {{ $pos->id }}, {{ $gong->id }})"
                                                           class="rounded border-border bg-surface-2 text-amber-500 focus:ring-amber-500 h-4 w-4 cursor-pointer" />
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{-- Ordered shot list (drag-and-drop) --}}
                        @if($ts->shotSequence->isNotEmpty())
                            <div class="rounded-lg border border-border bg-surface-2/20 p-3"
                                 x-data="{
                                     initSortable() {
                                         if (typeof Sortable === 'undefined') return;
                                         Sortable.create(this.$refs.list, {
                                             animation: 150,
                                             handle: '.drag-handle',
                                             ghostClass: 'opacity-30',
                                             onEnd: (evt) => {
                                                 const ids = [...this.$refs.list.children].map(el => el.dataset.id);
                                                 $wire.reorderShots(ids);
                                             }
                                         });
                                     }
                                 }"
                                 x-init="$nextTick(() => initSortable())"
                                 wire:ignore.self>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-muted mb-2">Firing Order</p>
                                <div x-ref="list" class="space-y-1">
                                    @foreach($ts->shotSequence->sortBy('shot_number') as $seq)
                                        <div class="flex items-center gap-3 rounded px-2 py-1 text-sm {{ $loop->even ? 'bg-surface-2/30' : '' }}" data-id="{{ $seq->id }}">
                                            <span class="drag-handle cursor-grab active:cursor-grabbing text-muted hover:text-primary">
                                                <x-icon name="grip-vertical" class="h-4 w-4" />
                                            </span>
                                            <span class="w-6 text-right font-mono text-muted text-xs">{{ $seq->shot_number }}</span>
                                            <span class="font-medium text-primary">{{ $seq->position?->name ?? '?' }}</span>
                                            <span class="text-muted">&rarr;</span>
                                            <span class="text-secondary">{{ $seq->gong?->label ?? 'T'.$seq->gong?->number }}</span>
                                            @if($seq->gong?->distance_meters)
                                                <span class="text-xs text-muted">({{ $seq->gong->distance_meters }}m)</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                    @endif
                    </div>{{-- /x-show detailOpen --}}
                    </div>{{-- /x-data detailOpen --}}
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
                        <button wire:click="removeElrTarget({{ $target->id }})" wire:confirm="Remove this target?" class="text-xs text-red-400 hover:text-red-300">×</button>
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

        </div>{{-- /tab:stages --}}

        <div x-show="tab === 'config'" x-cloak class="space-y-6">

        {{-- Squads summary --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-primary">Squads</h2>
                <flux:button href="{{ route('admin.matches.squadding', $match) }}" variant="primary" size="sm" class="!bg-accent hover:!bg-accent-hover">
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

            @if($scoring_type === 'standard')
                <div class="rounded-lg border border-dashed border-amber-600/40 bg-amber-950/10 p-4 space-y-3">
                    <div>
                        <h3 class="text-sm font-semibold text-amber-200">Equipment sheet import (Royal Flush)</h3>
                        <p class="mt-1 text-xs text-muted">
                            Paste <strong>tab-separated</strong> rows from Excel or Google Sheets. Expected columns (16):
                            timestamp, name, caliber, bullet, bullet weight, action, barrel, trigger, chassis, muzzle, scope, mount, bipod, phone, SA ID, notes/share rifle.
                            Scientific notation on ID/phone cells is fixed automatically. Creates placeholder accounts
                            (<code class="text-[10px]">@import.invalid</code>) with confirmed registrations; optionally adds shooters to the <strong>Default</strong> squad.
                        </p>
                    </div>
                    <textarea wire:model="equipmentImportPaste" rows="6" placeholder="Paste rows from your sheet…"
                              class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 font-mono text-xs text-primary placeholder:text-muted/50"></textarea>
                    @error('equipmentImportPaste')
                        <p class="text-xs text-accent">{{ $message }}</p>
                    @enderror
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex cursor-pointer items-center gap-2 text-xs text-secondary">
                            <input type="checkbox" wire:model="equipmentImportFreeEntry" class="rounded border-border bg-surface-2 text-amber-500" />
                            Free entry (R0, confirmed)
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-xs text-secondary">
                            <input type="checkbox" wire:model="equipmentImportAddShooters" class="rounded border-border bg-surface-2 text-amber-500" />
                            Add to Default squad if missing
                        </label>
                    </div>
                    <flux:button type="button" wire:click="importEquipmentRegistrations" variant="primary" size="sm" class="!bg-amber-600 hover:!bg-amber-700"
                                 wire:confirm="Import registrations from the pasted sheet? Rows with the same SA ID update the same person.">
                        Run import
                    </flux:button>
                </div>
            @endif
        </div>

        {{-- Custom Registration Fields --}}
        <flux:separator />

        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-primary">Custom Registration Fields</h2>
                <p class="mt-1 text-xs text-muted">Add extra fields to the registration form. Shooters fill these in when signing up.</p>
            </div>

            @if(count($customFields) > 0)
                <div class="space-y-2">
                    @foreach($customFields as $cf)
                        <div class="flex items-center justify-between rounded-lg border border-border bg-surface-2/30 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-primary">{{ $cf['label'] }}</span>
                                    <span class="rounded bg-surface-2 px-1.5 py-0.5 text-[10px] font-medium text-muted uppercase">{{ $cf['type'] }}</span>
                                    @if($cf['is_required'])
                                        <span class="rounded bg-red-900/30 px-1.5 py-0.5 text-[10px] font-medium text-red-400">Required</span>
                                    @endif
                                    @if($cf['show_on_scoreboard'])
                                        <span class="rounded bg-blue-900/30 px-1.5 py-0.5 text-[10px] font-medium text-blue-400">Scoreboard</span>
                                    @endif
                                    @if($cf['show_on_results'])
                                        <span class="rounded bg-green-900/30 px-1.5 py-0.5 text-[10px] font-medium text-green-400">Results</span>
                                    @endif
                                </div>
                                @if($cf['type'] === 'select' && !empty($cf['options']))
                                    <p class="mt-0.5 text-xs text-muted">Options: {{ implode(', ', $cf['options']) }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <flux:button wire:click="editCustomField({{ $cf['id'] }})" variant="ghost" size="sm" class="!text-secondary hover:!text-primary">Edit</flux:button>
                                <flux:button wire:click="deleteCustomField({{ $cf['id'] }})" variant="ghost" size="sm" class="!text-red-400 hover:!text-red-300"
                                             wire:confirm="Remove this field? Existing responses will be deleted.">Remove</flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="rounded-lg border border-dashed border-border bg-surface-2/20 p-4 space-y-3">
                <h3 class="text-sm font-semibold text-secondary">{{ $editingCustomFieldId ? 'Edit Field' : 'Add Field' }}</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-secondary">Label *</label>
                        <input type="text" wire:model="cfLabel" placeholder="e.g. Division, T-shirt Size"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                        @error('cfLabel') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-muted mb-1">Type</label>
                        <select wire:model.live="cfType"
                                class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="select">Dropdown (Select)</option>
                            <option value="checkbox">Checkbox</option>
                        </select>
                    </div>
                </div>

                @if($cfType === 'select')
                    <div>
                        <label class="block text-xs font-medium text-muted mb-1">Options (comma-separated) *</label>
                        <input type="text" wire:model="cfOptions" placeholder="e.g. Open, Factory, Custom"
                               class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                        @error('cfOptions') <p class="mt-1 text-xs text-accent">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <label class="flex items-center gap-2 text-xs text-secondary">
                        <input type="checkbox" wire:model="cfRequired" class="rounded border-border bg-surface-2 text-accent focus:ring-accent h-3.5 w-3.5">
                        Mandatory
                    </label>
                    <label class="flex items-center gap-2 text-xs text-secondary">
                        <input type="checkbox" wire:model="cfShowScoreboard" class="rounded border-border bg-surface-2 text-blue-500 focus:ring-blue-500 h-3.5 w-3.5">
                        Show on scoreboard
                    </label>
                    <label class="flex items-center gap-2 text-xs text-secondary">
                        <input type="checkbox" wire:model="cfShowResults" class="rounded border-border bg-surface-2 text-green-500 focus:ring-green-500 h-3.5 w-3.5">
                        Show on results
                    </label>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <flux:button wire:click="saveCustomField" variant="primary" size="sm" class="!bg-accent hover:!bg-accent-hover">
                        {{ $editingCustomFieldId ? 'Update Field' : 'Add Field' }}
                    </flux:button>
                    @if($editingCustomFieldId)
                        <flux:button wire:click="cancelCustomFieldEdit" variant="ghost" size="sm" class="!text-secondary hover:!text-primary">Cancel</flux:button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Side Bet Buy-In --}}
        @if($match->side_bet_enabled && $match->royal_flush_enabled)
            <flux:separator />

            @php
                $sideBetLocked = in_array($match->status, [MatchStatus::Active, MatchStatus::Completed]);
                $allShootersForBet = $match->shooters()->with('squad')->orderBy('name')->get();
            @endphp

            <div class="rounded-xl border {{ $sideBetLocked ? 'border-amber-700/30' : 'border-amber-600/50' }} bg-gradient-to-br from-amber-900/10 to-surface p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary flex items-center gap-2">
                            Side Bet Buy-In
                            @if($sideBetLocked)
                                <span class="rounded-full bg-zinc-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-zinc-300">Locked</span>
                            @else
                                <span class="rounded-full bg-amber-600/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-400">Open</span>
                            @endif
                        </h2>
                        <p class="mt-1 text-xs text-muted">
                            @if($sideBetLocked)
                                Buy-in is locked once scoring starts. {{ count($sideBetShooterIds) }} {{ Str::plural('participant', count($sideBetShooterIds)) }} registered.
                            @else
                                Select shooters who bought in this morning. Locked once scoring starts.
                            @endif
                        </p>
                    </div>
                    @if(! $sideBetLocked)
                        <flux:button wire:click="saveSideBetParticipants" variant="primary" size="sm" class="!bg-amber-600 hover:!bg-amber-700">
                            Save Participants
                        </flux:button>
                    @endif
                </div>

                @if($allShootersForBet->isNotEmpty())
                    <div class="rounded-lg border border-border bg-surface-2/30 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-medium text-secondary">{{ count($sideBetShooterIds) }} of {{ $allShootersForBet->count() }} shooters bought in</p>
                        </div>
                        <div class="max-h-64 overflow-y-auto space-y-1">
                            @foreach($allShootersForBet as $sh)
                                <label class="flex items-center gap-3 rounded-lg px-2 py-1.5 transition-colors {{ $sideBetLocked ? 'opacity-60' : 'hover:bg-surface-2/50 cursor-pointer' }}">
                                    <input type="checkbox" value="{{ $sh->id }}" wire:model="sideBetShooterIds"
                                           {{ $sideBetLocked ? 'disabled' : '' }}
                                           class="rounded border-slate-600 bg-surface-2 text-amber-500 focus:ring-amber-500 focus:ring-offset-0 h-4 w-4" />
                                    <span class="text-sm {{ $sideBetLocked && ! in_array($sh->id, $sideBetShooterIds) ? 'text-muted' : 'text-primary' }}">{{ $sh->name }}</span>
                                    <span class="text-[10px] text-muted">({{ $sh->squad?->name ?? '—' }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="rounded-lg border border-border bg-surface-2/30 px-4 py-6 text-center">
                        <p class="text-sm text-muted">No shooters yet. Add squads and shooters first.</p>
                    </div>
                @endif
            </div>
        @endif
        </div>{{-- /tab:config group 2 --}}
    @endif
</div>

@once
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
@endonce
