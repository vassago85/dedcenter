<?php

use App\Models\Organization;
use App\Models\ShootingMatch;
use App\Models\User;
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
use App\Enums\Province;
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

    public Organization $organization;
    public ?ShootingMatch $match = null;
    public $coverImage = null;

    public string $name = '';
    public string $date = '';
    public string $location = '';
    public string $province = '';
    public string $notes = '';
    public string $public_bio = '';
    public string $entry_fee = '';
    public string $registration_closes_at = '';
    public string $scoring_type = 'standard';
    public bool $scores_published = true;
    public bool $royal_flush_enabled = false;
    public bool $side_bet_enabled = false;
    public int $concurrent_relays = 2;
    public bool $self_squadding_enabled = true;
    public bool $team_event = false;
    public int $team_size = 3;
    public string $corrections_pin = '';

    public array $registrationFieldsConfig = [
        'rifle' => 'hidden',
        'ammo' => 'hidden',
        'division' => 'optional',
        'category' => 'optional',
        'emergency_contact' => 'hidden',
    ];

    public int $matchDays = 1;

    public string $staffEmail = '';

    public string $staffRole = 'range_officer';

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

    // Side Bet
    public array $sideBetShooterIds = [];

    // Custom Registration Fields
    public array $customFields = [];
    public string $cfLabel = '';
    public string $cfType = 'text';
    public string $cfOptions = '';
    public bool $cfRequired = false;
    public bool $cfShowScoreboard = false;
    public bool $cfShowResults = false;
    public ?int $editingCustomFieldId = null;

    // Team management
    public string $newTeamName = '';

    // DQ
    public ?int $dqShooterId = null;
    public ?int $dqTargetSetId = null;
    public string $dqReason = '';
    public bool $showDqModal = false;

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

    // Messaging
    public string $msgSubject = '';
    public string $msgBody = '';
    public string $msgAudience = 'all';

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
            $this->province = $match->province?->value ?? '';
            $this->notes = $match->notes ?? '';
            $this->public_bio = $match->public_bio ?? '';
            $this->entry_fee = $match->entry_fee ? (string) $match->entry_fee : '';
            $this->registration_closes_at = $match->registration_closes_at?->format('Y-m-d\TH:i') ?? '';
            $this->scoring_type = in_array($match->scoring_type, ['standard', 'prs', 'elr'], true)
                ? $match->scoring_type
                : 'standard';
            $this->concurrent_relays = $match->concurrent_relays ?? 2;
            $this->scores_published = (bool) ($match->scores_published ?? true);
            $this->royal_flush_enabled = (bool) $match->royal_flush_enabled;
            $this->side_bet_enabled = (bool) $match->side_bet_enabled;
            $this->corrections_pin = $match->corrections_pin ?? '';
            $this->self_squadding_enabled = (bool) ($match->self_squadding_enabled ?? true);
            $this->team_event = (bool) $match->team_event;
            $this->team_size = $match->team_size ?? 3;
            $this->sideBetShooterIds = $match->sideBetShooters()->pluck('shooters.id')->map(fn ($id) => (int) $id)->toArray();
            $this->registrationFieldsConfig = $match->registration_fields_config ?? $this->registrationFieldsConfig;
            $this->matchDays = $match->match_days ?? 1;
            $this->loadCustomFields();
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
            'province' => 'nullable|string|in:' . implode(',', array_column(Province::cases(), 'value')),
            'notes' => 'nullable|string|max:5000',
            'public_bio' => 'nullable|string|max:2000',
            'entry_fee' => 'nullable|numeric|min:0',
            'registration_closes_at' => 'nullable|date',
            'scoring_type' => 'required|in:standard,prs,elr',
            'corrections_pin' => 'nullable|string|min:4|max:6|regex:/^\d+$/',
            'coverImage' => 'nullable|image|max:4096',
        ]);

        $validated['entry_fee'] = $this->entry_fee !== '' ? (float) $this->entry_fee : null;
        $validated['registration_closes_at'] = $this->registration_closes_at !== '' ? $this->registration_closes_at : null;
        $validated['province'] = $this->province !== '' ? $this->province : null;
        $orgIsRf = $this->organization->isRoyalFlushOrg();
        $validated['royal_flush_enabled'] = $orgIsRf && $this->scoring_type === 'standard' && $this->royal_flush_enabled;
        $validated['side_bet_enabled'] = $validated['royal_flush_enabled'] && $this->side_bet_enabled;
        $validated['concurrent_relays'] = $this->scoring_type === 'standard' ? max(1, $this->concurrent_relays) : 1;
        $validated['scores_published'] = $this->scores_published;
        $validated['corrections_pin'] = $this->corrections_pin !== '' ? $this->corrections_pin : null;
        $validated['self_squadding_enabled'] = $this->self_squadding_enabled;
        $validated['team_event'] = $this->team_event;
        $validated['team_size'] = max(2, $this->team_size);
        $validated['registration_fields_config'] = $this->registrationFieldsConfig;
        $validated['match_days'] = max(1, $this->matchDays);

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

            if (! $validated['side_bet_enabled']) {
                $this->match->sideBetShooters()->detach();
                $this->sideBetShooterIds = [];
            }

            Flux::toast('Match updated.', variant: 'success');
        } else {
            $this->match = ShootingMatch::create([
                ...$validated,
                'organization_id' => $this->organization->id,
                'created_by' => auth()->id(),
                'status' => MatchStatus::Draft,
            ]);

            if ($this->match->image_url && str_contains($this->match->image_url, 'match-covers/new/')) {
                $newPath = str_replace('match-covers/new/', "match-covers/{$this->match->id}/", $this->match->image_url);
                Storage::disk('public')->move($this->match->image_url, $newPath);
                $this->match->update(['image_url' => $newPath]);
            }

            Flux::toast('Match created.', variant: 'success');
            $this->redirect(route('org.matches.edit', [$this->organization, $this->match]), navigate: true);
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

        if ($this->match->status === MatchStatus::Completed) {
            Flux::toast('Buy-in is locked once the match is completed.', variant: 'danger');
            return;
        }

        $this->match->sideBetShooters()->sync($this->sideBetShooterIds);
        Flux::toast('Side bet participants saved.', variant: 'success');
    }

    public function requestFeatured(): void
    {
        if (! $this->match) return;
        $this->match->update(['featured_status' => 'requested']);
        Flux::toast('Featured listing requested — you will be contacted for payment.', variant: 'success');
    }

    public function cancelFeaturedRequest(): void
    {
        if (! $this->match) return;
        $this->match->update(['featured_status' => null]);
        Flux::toast('Featured request cancelled.', variant: 'success');
    }

    public function addTeam(): void
    {
        if (! $this->match) return;
        $this->validate(['newTeamName' => 'required|string|max:255']);
        $maxSort = $this->match->teams()->max('sort_order') ?? 0;
        $this->match->teams()->create([
            'name' => $this->newTeamName,
            'max_size' => $this->team_size,
            'sort_order' => $maxSort + 1,
        ]);
        $this->reset('newTeamName');
        Flux::toast('Team added.', variant: 'success');
    }

    public function deleteTeam(int $id): void
    {
        if (! $this->match) return;
        $team = $this->match->teams()->findOrFail($id);
        \App\Models\Shooter::where('team_id', $team->id)->update(['team_id' => null]);
        $team->delete();
        Flux::toast('Team removed.', variant: 'success');
    }

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
        $this->createTargetSet((int) $this->tsDistance);
        $this->reset('tsDistance');
        Flux::toast('Target set added.', variant: 'success');
    }

    /**
     * Variant that accepts the distance as an explicit argument so the caller
     * doesn't need to rely on wire:model being synced. Used by the Alpine-bound
     * "Add Target Set" button.
     */
    public function addTargetSetValue($distance): void
    {
        if ($this->scoring_type === 'prs') {
            Flux::toast('This button is for relay scoring. Use Add Stage for PRS.', variant: 'warning');
            return;
        }

        $distance = (int) $distance;
        if ($distance < 1) {
            Flux::toast('Enter a valid distance in metres.', variant: 'warning');
            return;
        }

        $this->createTargetSet($distance);
        Flux::toast("Added {$distance}m target set.", variant: 'success');
    }

    /**
     * Quick preset for Royal Flush matches: create 400/500/600/700 m target
     * sets, each with 5 gongs using the canonical RF multiplier table
     * (G1:1.00, G2:1.30, G3:1.50, G4:1.80, G5:2.00). Idempotent — fills
     * missing distances and snaps existing gong multipliers to the table.
     */
    public function addRoyalFlushPresets(): void
    {
        if (! $this->match) {
            Flux::toast('Save the match first before adding presets.', variant: 'warning');
            return;
        }

        $gongMultipliers = ['1.00', '1.30', '1.50', '1.80', '2.00'];
        $created = 0;
        foreach ([400, 500, 600, 700] as $distance) {
            $ts = $this->match->targetSets()->where('distance_meters', $distance)->first();
            $isNew = ! $ts;
            if ($isNew) {
                $ts = $this->createTargetSet($distance);
                $created++;
            }
            $existing = \App\Models\Gong::where('target_set_id', $ts->id)->orderBy('number')->get()->keyBy('number');
            for ($n = 1; $n <= 5; $n++) {
                $mult = $gongMultipliers[$n - 1];
                if ($existing->has($n)) {
                    $existing[$n]->fill(['label' => "G{$n}", 'multiplier' => $mult])->save();
                } else {
                    \App\Models\Gong::create([
                        'target_set_id' => $ts->id,
                        'number' => $n,
                        'label' => "G{$n}",
                        'multiplier' => $mult,
                    ]);
                }
            }
        }

        if ($created === 0) {
            Flux::toast('RF distances were already present — multipliers refreshed to the RF table.', variant: 'success');
        } else {
            Flux::toast("Added {$created} Royal Flush target set(s) with RF multipliers.", variant: 'success');
        }
    }

    protected function createTargetSet(int $distance): \App\Models\TargetSet
    {
        $maxSort = $this->match->targetSets()->max('sort_order') ?? 0;
        return $this->match->targetSets()->create([
            'label' => "{$distance}m",
            'distance_meters' => $distance,
            'distance_multiplier' => $distance / 100,
            'sort_order' => $maxSort + 1,
        ]);
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
        } elseif ($field === 'match_day') {
            $ts->update(['match_day' => max(1, (int) $value)]);
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
                'number' => $gong->number,
                'label' => $gong->label,
                'multiplier' => $gong->multiplier,
                'distance_meters' => $gong->distance_meters,
                'target_size' => $gong->target_size,
                'target_size_mm' => $gong->target_size_mm,
            ]);
            $gongMap[$gong->id] = $newGong->id;
        }

        $posMap = [];
        foreach ($source->positions as $pos) {
            $newPos = $clone->positions()->create([
                'name' => $pos->name,
                'sort_order' => $pos->sort_order,
            ]);
            $posMap[$pos->id] = $newPos->id;
        }

        foreach ($source->shotSequence as $seq) {
            if (isset($posMap[$seq->position_id]) && isset($gongMap[$seq->gong_id])) {
                $clone->shotSequence()->create([
                    'shot_number' => $seq->shot_number,
                    'position_id' => $posMap[$seq->position_id],
                    'gong_id' => $gongMap[$seq->gong_id],
                ]);
            }
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
        $ts->positions()->create([
            'name' => 'Position ' . ($maxSort + 1),
            'sort_order' => $maxSort + 1,
        ]);
        Flux::toast('Position added.', variant: 'success');
    }

    public function updatePosition(int $positionId, string $name): void
    {
        $pos = StagePosition::findOrFail($positionId);
        $pos->update(['name' => $name]);
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
        $existing = StageShotSequence::where('stage_id', $stageId)
            ->where('position_id', $positionId)
            ->where('gong_id', $gongId)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->resequenceShotNumbers($stageId);
        } else {
            $maxShot = StageShotSequence::where('stage_id', $stageId)->max('shot_number') ?? 0;
            StageShotSequence::create([
                'stage_id' => $stageId,
                'shot_number' => $maxShot + 1,
                'position_id' => $positionId,
                'gong_id' => $gongId,
            ]);
        }

        $this->syncPrsTargetCount($stageId);
    }

    public function reorderShotSequence(int $stageId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            StageShotSequence::where('id', $id)->where('stage_id', $stageId)
                ->update(['shot_number' => $index + 1]);
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
        $shotNumber = 1;

        foreach ($positions as $pos) {
            foreach ($gongs as $gong) {
                StageShotSequence::create([
                    'stage_id' => $stageId,
                    'shot_number' => $shotNumber++,
                    'position_id' => $pos->id,
                    'gong_id' => $gong->id,
                ]);
            }
        }

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
        foreach ($rows->values() as $i => $row) {
            if ($row->shot_number !== $i + 1) {
                $row->update(['shot_number' => $i + 1]);
            }
        }
    }

    public function updateGongTargetSizeMm(int $gongId, string $value): void
    {
        $gong = Gong::findOrFail($gongId);
        $gong->update(['target_size_mm' => $value !== '' ? max(0.01, (float) $value) : null]);
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

    public function updateElrStageDay(int $stageId, string $value): void
    {
        $this->match->elrStages()->where('id', $stageId)->update(['match_day' => max(1, (int) $value)]);
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
        $shooter->update(['status' => $shooter->isActive() ? 'withdrawn' : 'active']);
        Flux::toast('Shooter ' . ($shooter->isActive() ? 'reactivated' : 'withdrawn') . '.', variant: 'success');
    }

    public function deleteShooter(int $id): void { Shooter::destroy($id); Flux::toast('Shooter removed permanently.', variant: 'success'); }

    public function openDqModal(int $shooterId, ?int $targetSetId = null): void
    {
        $this->dqShooterId = $shooterId;
        $this->dqTargetSetId = $targetSetId;
        $this->dqReason = '';
        $this->showDqModal = true;
    }

    public function issueDq(): void
    {
        $this->validate(['dqReason' => 'required|string|min:5|max:1000']);

        $shooter = Shooter::findOrFail($this->dqShooterId);
        $isMatchDq = $this->dqTargetSetId === null;

        $existing = \App\Models\Disqualification::where('match_id', $this->match->id)
            ->where('shooter_id', $shooter->id)
            ->where('target_set_id', $this->dqTargetSetId)
            ->exists();

        if ($existing) {
            Flux::toast('This DQ already exists.', variant: 'danger');
            $this->showDqModal = false;
            return;
        }

        \App\Models\Disqualification::create([
            'match_id' => $this->match->id,
            'shooter_id' => $shooter->id,
            'target_set_id' => $this->dqTargetSetId,
            'reason' => $this->dqReason,
            'issued_by' => auth()->id(),
        ]);

        if ($isMatchDq) {
            $shooter->update(['status' => 'dq']);
        }

        \App\Services\ScoreAuditService::log(
            $this->match->id,
            $shooter,
            $isMatchDq ? 'match_dq' : 'stage_dq',
            null,
            ['target_set_id' => $this->dqTargetSetId, 'reason' => $this->dqReason],
            $this->dqReason,
        );

        $this->showDqModal = false;
        $type = $isMatchDq ? 'match' : 'stage';
        Flux::toast("{$shooter->name} disqualified ({$type} DQ).", variant: 'danger');
    }

    public function revokeDq(int $dqId): void
    {
        $dq = \App\Models\Disqualification::where('match_id', $this->match->id)->findOrFail($dqId);
        $shooter = $dq->shooter;
        $wasMatchDq = $dq->isMatchDq();

        \App\Services\ScoreAuditService::log(
            $this->match->id,
            $shooter,
            'dq_revoked',
            ['target_set_id' => $dq->target_set_id, 'reason' => $dq->reason],
            null,
            "DQ revoked by " . auth()->user()->name,
        );

        $dq->delete();

        if ($wasMatchDq) {
            $hasOther = \App\Models\Disqualification::where('match_id', $this->match->id)
                ->where('shooter_id', $shooter->id)
                ->whereNull('target_set_id')
                ->exists();
            if (! $hasOther) {
                $shooter->update(['status' => 'active']);
            }
        }

        Flux::toast("{$shooter->name}'s DQ has been revoked.", variant: 'success');
    }

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
        $oldStatus = $this->match->status;
        $isUncomplete = $oldStatus === MatchStatus::Completed && $targetStatus === MatchStatus::Active;
        $this->match->update(['status' => $targetStatus]);

        try {
            app(\App\Services\NotificationService::class)->onStatusChange($this->match, $oldStatus, $targetStatus);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Status notification dispatch failed', ['error' => $e->getMessage()]);
        }

        if ($targetStatus === MatchStatus::RegistrationClosed) {
            $this->cleanUpPreRegistrations();
        }

        // Only evaluate achievements on a fresh entry into Completed (forward
        // walk). Un-completing and then re-completing should not trigger
        // another evaluation pass — the evaluators are idempotent on
        // slug+shooter, but there's no value in running them again.
        if ($targetStatus === MatchStatus::Completed && $oldStatus !== MatchStatus::Completed) {
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

        if ($isUncomplete) {
            Flux::toast('Match reopened. Achievements already awarded stay in place.', variant: 'success');
        } else {
            Flux::toast("Match status changed to {$targetStatus->label()}.", variant: 'success');
        }
    }

    public function reopenMatch(): void { $this->match->update(['status' => MatchStatus::Active]); Flux::toast('Match reopened.', variant: 'success'); }

    protected function cleanUpPreRegistrations(): void
    {
        $removed = $this->match->registrations()
            ->where('payment_status', 'pre_registered')
            ->delete();

        if ($removed > 0) {
            Flux::toast("{$removed} incomplete pre-registration(s) removed.", variant: 'warning');
        }
    }

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
            if ($this->scores_published) {
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

                if ($this->match->status === \App\Enums\MatchStatus::Completed) {
                    \App\Jobs\SendPostMatchNotifications::dispatch($this->match)->delay(now()->addHour());
                }
            }
            Flux::toast($this->scores_published ? 'Scores are now live.' : 'Scores hidden from public.', variant: 'success');
        }
    }

    public function sendMessage(): void
    {
        $this->validate([
            'msgSubject' => 'required|string|max:255',
            'msgBody' => 'required|string|max:5000',
            'msgAudience' => 'required|in:all,confirmed,squad',
        ]);

        $message = $this->match->messages()->create([
            'sent_by' => auth()->id(),
            'subject' => $this->msgSubject,
            'body' => $this->msgBody,
            'audience' => $this->msgAudience,
            'sent_at' => now(),
        ]);

        $registrations = $this->match->registrations()->with('user');

        if ($this->msgAudience === 'confirmed') {
            $registrations->where('payment_status', 'confirmed');
        }

        $users = $registrations->get()->pluck('user')->filter()->unique('id');

        foreach ($users as $user) {
            if ($user->wantsNotification('match_updates')) {
                $user->notify(new \App\Notifications\MatchAnnouncementNotification($message));
            }
        }

        $this->msgSubject = '';
        $this->msgBody = '';
        $this->msgAudience = 'all';

        \Flux\Flux::toast("Message sent to {$users->count()} shooters.", variant: 'success');
    }

    public function with(): array
    {
        $data = ['divisions' => collect(), 'categories' => collect(), 'qrCodeSvg' => null];
        if ($this->match) {
            $data['targetSets'] = $this->match->targetSets()->with(['gongs', 'positions', 'shotSequence'])->orderBy('sort_order')->get();
            $data['squads'] = $this->match->squads()->with(['shooters.division', 'shooters.categories'])->orderBy('sort_order')->get();
            $data['disqualifications'] = $this->match->disqualifications()->with(['shooter:id,name', 'targetSet:id,label,stage_number', 'issuedBy:id,name'])->latest()->get();
            $data['divisions'] = $this->match->divisions()->orderBy('sort_order')->get();
            $data['categories'] = $this->match->categories()->orderBy('sort_order')->get();
            $data['messages'] = $this->match->messages()->with('sender')->latest()->take(20)->get();

            if (in_array($this->match->status, [MatchStatus::Active, MatchStatus::Completed, MatchStatus::SquaddingOpen, MatchStatus::SquaddingClosed, MatchStatus::Ready])) {
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
        <flux:button href="{{ route('org.matches.index', $organization) }}" variant="ghost" size="sm">
            <x-icon name="chevron-left" class="mr-1 h-4 w-4" />
            Back
        </flux:button>
        <div>
            <flux:heading size="xl">{{ $match ? 'Edit Match' : 'New Match' }}</flux:heading>
            <p class="mt-1 text-sm text-muted">{{ $organization->name }}</p>
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
    @if($match)
        {{-- Quick-status card: shows current lifecycle state and the single
             most-useful next action. Keeps MDs out of the Config tab just to
             flip a status. --}}
        @php
            $statusCardClasses = match($match->status) {
                \App\Enums\MatchStatus::Active => 'border-green-500/40 bg-green-950/20',
                \App\Enums\MatchStatus::Completed => 'border-zinc-500/30 bg-zinc-900/30',
                \App\Enums\MatchStatus::SquaddingOpen => 'border-indigo-500/40 bg-indigo-950/20',
                \App\Enums\MatchStatus::SquaddingClosed => 'border-cyan-500/40 bg-cyan-950/20',
                \App\Enums\MatchStatus::Ready => 'border-emerald-500/40 bg-emerald-950/20',
                default => 'border-amber-500/30 bg-amber-950/15',
            };
        @endphp
        <div class="mb-6 rounded-xl border {{ $statusCardClasses }} p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-muted">Current Status</div>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-{{ $match->status->color() }}-600/25 px-3 py-1 text-sm font-semibold text-{{ $match->status->color() }}-300">
                            {{ $match->status->label() }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-secondary">
                        @switch($match->status)
                            @case(\App\Enums\MatchStatus::Draft)
                                This match is a draft. Open registration or squadding to progress.
                                @break
                            @case(\App\Enums\MatchStatus::PreRegistration)
                            @case(\App\Enums\MatchStatus::RegistrationOpen)
                                Shooters are registering. Close registration when ready to squad.
                                @break
                            @case(\App\Enums\MatchStatus::RegistrationClosed)
                                Registration closed. Open squadding or start the match.
                                @break
                            @case(\App\Enums\MatchStatus::SquaddingOpen)
                                Squadding is open. Close squadding to lock shooter self-squadding.
                                @break
                            @case(\App\Enums\MatchStatus::SquaddingClosed)
                                Squads are locked to shooters. Finalise squad assignments, then mark the match <strong class="text-primary">Ready</strong>.
                                @break
                            @case(\App\Enums\MatchStatus::Ready)
                                Pre-flight done. Tablets can download the match. Scoring goes live on the first shot or when you tap <strong class="text-primary">Start Match</strong>.
                                @break
                            @case(\App\Enums\MatchStatus::Active)
                                Match is live. Scoring app can download and submit scores.
                                @break
                            @case(\App\Enums\MatchStatus::Completed)
                                Match completed. Scores are locked unless you reopen it.
                                @break
                        @endswitch
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    {{-- Primary CTA: Mark Ready from SquaddingClosed, Start Match from Ready,
                         Start Match (straight to Active) from earlier stages. --}}
                    @if($match->status === \App\Enums\MatchStatus::SquaddingClosed)
                        <button type="button" wire:click="transitionStatus('ready')"
                                wire:confirm="Mark this match as Ready? Tablets will be able to download it. Scoring is still locked until the first shot or tapping Start Match."
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-900/40 transition-colors min-h-[44px]">
                            <x-icon name="chevron-right" class="h-4 w-4" />
                            Mark Ready
                        </button>
                    @elseif(in_array(\App\Enums\MatchStatus::Active, $match->status->allowedTransitions()))
                        <button type="button" wire:click="transitionStatus('active')"
                                wire:confirm="Start the match now? Scoring goes live and the scoreboard begins accepting hits."
                                class="inline-flex items-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-green-900/40 transition-colors min-h-[44px]">
                            <x-icon name="chevron-right" class="h-4 w-4" />
                            Start Match
                        </button>
                    @endif
                    @if(in_array($match->status, [\App\Enums\MatchStatus::Active, \App\Enums\MatchStatus::Ready]))
                        <flux:button href="{{ route('score') }}" target="_blank" variant="primary" class="!bg-accent hover:!bg-accent-hover">Open Scoring App</flux:button>
                    @endif
                    <flux:button href="{{ route('org.matches.squadding', [$organization, $match]) }}" variant="ghost" size="sm">Squadding</flux:button>
                </div>
            </div>
        </div>
    @endif
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Match Details</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Name" placeholder="e.g. Monthly Steel Challenge" required />
                <flux:input wire:model="date" label="Date" type="date" required />
            </div>
            <div class="max-w-xs">
                <label class="block text-sm font-medium text-secondary mb-1">Match Days</label>
                <input type="number" wire:model="matchDays" min="1" max="5" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary" />
                <p class="mt-1 text-xs text-muted">Set to 2+ for multi-day events. Stages can be assigned to specific days.</p>
            </div>
            <p class="text-xs text-muted">
                Matches still <strong>Active</strong> or <strong>Squadding Open</strong> are automatically set to <strong>Completed</strong> the day after this date ({{ config('app.timezone') }}). Update the date if the event moves.
            </p>
            <flux:input wire:model="location" label="Location" placeholder="e.g. Range 3, Pretoria" />
            <div>
                <label class="block text-sm font-medium text-secondary mb-1">Province</label>
                <select wire:model="province" class="w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-accent focus:ring-1 focus:ring-accent">
                    <option value="">— Select province —</option>
                    @foreach(\App\Enums\Province::cases() as $p)
                        <option value="{{ $p->value }}">{{ $p->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <flux:input wire:model="entry_fee" label="Entry Fee (ZAR)" type="number" step="0.01" min="0" placeholder="Leave empty for free" />
                    <p class="mt-1 text-xs text-muted">Leave empty or 0 for free entry.</p>
                </div>
                <div>
                    <flux:input wire:model="registration_closes_at" label="Registration Closes" type="datetime-local" />
                    <p class="mt-1 text-xs text-muted">Defaults to 72 hours before the event if left empty. After this, pre-registered shooters who haven't completed registration are removed.</p>
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
                    @if($organization->isRoyalFlushOrg())
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
                                    @if($match->shooters()->exists())
                                        <p class="text-xs text-muted italic">Scroll down to the <strong class="text-primary">Side Bet Buy-In</strong> panel to tick each shooter who paid in.</p>
                                    @else
                                        <p class="text-xs text-muted italic">Add squads and shooters first, then select side bet participants.</p>
                                    @endif
                                @endif
                            </div>
                        @endif
                    @endif

                    <div class="{{ $organization->isRoyalFlushOrg() ? 'border-t border-border pt-4' : '' }}">
                        <div class="flex items-center gap-4">
                            <div class="w-32">
                                <label class="block text-sm font-medium text-secondary mb-1">Concurrent Relays</label>
                                <input type="number" wire:model="concurrent_relays" min="1" max="10"
                                       class="w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-primary text-center focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                            </div>
                            <p class="text-xs text-muted flex-1">How many relays shoot at the same time. Shared-rifle partners will be placed in different concurrent groups.</p>
                        </div>
                    </div>

                    <div class="border-t border-border pt-4">
                        <flux:switch wire:model.live="self_squadding_enabled" label="Self-Squadding" description="Allow shooters to pick their own squad when squadding opens" />
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
                $currentStatus = $match->status;
                $currentOrd = $currentStatus->ordinal();
                $allowed = $currentStatus->allowedTransitions();
            @endphp

            <p class="text-sm text-muted">Tap any stage to jump there. The current stage is ringed. Every jump asks for confirmation and tells you what will fire.</p>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($steps as $step)
                    @php
                        $ord = $step->ordinal();
                        $isCurrent = $currentStatus === $step;
                        $isPast = $ord < $currentOrd && ! $isCurrent;
                        $isAllowed = in_array($step, $allowed, true);
                        $color = $step->color();

                        $warning = $step->transitionWarning($currentStatus);
                        $confirmText = $isCurrent
                            ? null
                            : "Move match to '{$step->label()}'?\n\n".($warning ?? 'No side effects.');
                    @endphp

                    <button
                        @if(! $isCurrent && $isAllowed)
                            wire:click="transitionStatus('{{ $step->value }}')"
                            wire:confirm="{{ $confirmText }}"
                        @else
                            type="button"
                            disabled
                        @endif
                        class="group relative flex items-start gap-3 rounded-xl border p-3 text-left transition-colors
                            {{ $isCurrent
                                ? 'border-'.$color.'-500 bg-'.$color.'-600/15 ring-2 ring-'.$color.'-500/40 cursor-default'
                                : ($isAllowed
                                    ? ($isPast
                                        ? 'border-green-600/40 bg-green-600/5 hover:bg-green-600/15 hover:border-green-500/60'
                                        : 'border-border bg-surface-2/40 hover:bg-surface-2 hover:border-'.$color.'-500/50')
                                    : 'border-border/40 bg-surface-2/10 opacity-40 cursor-not-allowed')
                            }}"
                    >
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold
                            {{ $isCurrent
                                ? 'bg-'.$color.'-600 text-white'
                                : ($isPast ? 'bg-green-600/30 text-green-400' : 'bg-surface-2 text-muted') }}">
                            @if($isPast)
                                &#10003;
                            @else
                                {{ $ord + 1 }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold {{ $isCurrent ? 'text-primary' : 'text-secondary' }}">{{ $step->label() }}</span>
                                @if($isCurrent)
                                    <span class="rounded-full bg-{{ $color }}-600/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-{{ $color }}-300">Current</span>
                                @elseif($isPast)
                                    <span class="text-[10px] text-green-400">Done</span>
                                @elseif(! $isAllowed)
                                    <span class="text-[10px] text-muted">Not reachable</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs leading-snug text-muted">{{ $step->shortDescription() }}</p>
                        </div>
                    </button>
                @endforeach
            </div>

            @if($currentStatus === MatchStatus::Active)
                <div class="rounded-xl border border-red-600/30 bg-red-900/10 p-5 space-y-3">
                    <h3 class="text-base font-semibold text-red-400">Finalise Match</h3>
                    <p class="text-sm text-muted">When every stage is scored and every time recorded, mark the match Completed above. This will trigger badge evaluation and post-match notifications. You can un-complete it later if needed — achievements already awarded will stay in place.</p>
                </div>
            @elseif($currentStatus === MatchStatus::Completed)
                <div class="rounded-xl border border-amber-600/30 bg-amber-900/10 p-5 space-y-2">
                    <h3 class="text-base font-semibold text-amber-400">Match Completed</h3>
                    <p class="text-sm text-muted">Accidentally finished the match? Tap <span class="font-semibold text-primary">Active</span> above to reopen it. Achievements already awarded and post-match emails already sent will stay in place — re-completing later does not re-award or re-send.</p>
                </div>
            @endif

            <div class="flex flex-wrap gap-3 border-t border-border pt-4">
                <flux:button href="{{ route('org.matches.squadding', [$organization, $match]) }}" variant="ghost">Manage Squadding</flux:button>
                @if(in_array($match->status, [MatchStatus::Active, MatchStatus::Completed, MatchStatus::SquaddingOpen, MatchStatus::SquaddingClosed, MatchStatus::Ready]))
                    <flux:button href="{{ route('score') }}" target="_blank" variant="ghost">Open Scoring</flux:button>
                    <flux:button href="{{ route('scoreboard', $match) }}" target="_blank" variant="ghost">View Scoreboard</flux:button>
                @endif
                @if(in_array($match->status, [MatchStatus::Active, MatchStatus::Completed]))
                    <flux:button href="{{ route('org.matches.export.standings', [$organization, $match]) }}" variant="ghost">Download Standings</flux:button>
                    <flux:button href="{{ route('org.matches.export.detailed', [$organization, $match]) }}" variant="ghost">Download Full Results</flux:button>
                    @if($match->side_bet_enabled && $match->royal_flush_enabled)
                        <flux:button href="{{ route('org.matches.side-bet-report', [$organization, $match]) }}" variant="ghost" class="!text-amber-400">Side Bet Report</flux:button>
                    @endif
                    <flux:button wire:click="toggleScoresPublished" variant="{{ $scores_published ? 'ghost' : 'primary' }}" class="{{ $scores_published ? '' : '!bg-amber-600 hover:!bg-amber-700' }}">
                        {{ $scores_published ? 'Hide Scores' : 'Publish Scores' }}
                    </flux:button>
                @endif
                @if($match->status === MatchStatus::Completed)
                    <flux:button href="{{ route('org.matches.report.preview', [$organization, $match]) }}" target="_blank" variant="ghost">Preview Match Report</flux:button>
                    <flux:button wire:click="sendMatchReports" wire:confirm="Results and reports are sent automatically 1 hour after marking complete. Send them now instead?" variant="primary" class="!bg-emerald-600 hover:!bg-emerald-700">
                        Send Reports Now
                    </flux:button>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Results auto-sent 1 hr after completion</p>
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
            <h2 class="text-lg font-semibold text-primary">Advertising Options</h2>
            <p class="text-sm text-muted">Control advertising placements for this event. Brands pay for visibility — not event sponsorship.</p>
            @livewire('match-advertising-options', ['match' => $match], key('match-advertising-' . $match->id))
        </div>

        {{-- Featured Event --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-primary">Featured Event</h2>
                @if($match->isFeatured())
                    <span class="rounded-full bg-amber-500/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-400">Active</span>
                @elseif($match->isFeatureRequested())
                    <span class="rounded-full bg-blue-500/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-blue-400">Pending</span>
                @endif
            </div>

            @if($match->isFeatured())
                <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-4">
                    <p class="text-sm text-emerald-400 font-medium">This event is featured on the homepage and ranks higher in event listings.</p>
                    @if($match->featured_until)
                        <p class="mt-1 text-xs text-muted">Featured until {{ $match->featured_until->format('d M Y') }}</p>
                    @endif
                </div>
            @elseif($match->isFeatureRequested())
                <div class="rounded-lg border border-blue-500/30 bg-blue-500/5 p-4 space-y-3">
                    <p class="text-sm text-blue-400 font-medium">Your featured listing request has been submitted.</p>
                    <p class="text-xs text-muted">You will be contacted by the platform administrator for payment of R{{ number_format(\App\Models\ShootingMatch::featurePrice()) }}. Once confirmed, your event will be featured.</p>
                    <flux:button wire:click="cancelFeaturedRequest" variant="ghost" size="sm">Cancel Request</flux:button>
                </div>
            @else
                <p class="text-sm text-muted">Get your event more visibility by featuring it on the homepage and at the top of event listings.</p>
                <div class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-primary">Featured listing</span>
                        <span class="text-lg font-bold text-amber-400">R{{ number_format(\App\Models\ShootingMatch::featurePrice()) }}</span>
                    </div>
                    <ul class="text-xs text-muted space-y-1 ml-4 list-disc">
                        <li>Highlighted on the homepage</li>
                        <li>Featured badge on your event card</li>
                        <li>Ranks higher in event listings</li>
                    </ul>
                </div>
                <flux:button wire:click="requestFeatured" variant="primary" size="sm">Request Featured Listing</flux:button>
            @endif
        </div>

        {{-- Team Event --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Team Event</h2>
            <flux:switch wire:model.live="team_event" label="This is a team event" description="Enable combined team scoring alongside individual results" />

            @if($team_event)
                <div class="space-y-4 border-t border-border pt-4">
                    <div class="w-32">
                        <flux:input wire:model.blur="team_size" label="Team Size" type="number" min="2" max="20" />
                    </div>
                    <p class="text-xs text-muted">Default number of shooters per team.</p>

                    @if($match)
                        @php $teams = $match->teams()->withCount('shooters')->orderBy('sort_order')->get(); @endphp
                        @if($teams->isNotEmpty())
                            <div class="space-y-2">
                                <h3 class="text-sm font-medium text-secondary">Teams</h3>
                                @foreach($teams as $team)
                                    <div class="flex items-center justify-between rounded-lg border border-border bg-surface-2/40 px-4 py-2" wire:key="team-{{ $team->id }}">
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm font-medium text-primary">{{ $team->name }}</span>
                                            <span class="text-xs text-muted">{{ $team->shooters_count }}/{{ $team->effectiveMaxSize() }} members</span>
                                        </div>
                                        <button wire:click="deleteTeam({{ $team->id }})" wire:confirm="Delete team {{ $team->name }}?"
                                                class="text-accent/60 hover:text-accent text-lg leading-none">&times;</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex gap-3 items-end">
                            <div class="flex-1"><flux:input wire:model="newTeamName" placeholder="e.g. Team Alpha" /></div>
                            <flux:button wire:click="addTeam" size="sm" variant="primary" class="!bg-accent hover:!bg-accent-hover">Add Team</flux:button>
                        </div>
                    @else
                        <p class="text-xs text-muted">Save the match first, then add teams.</p>
                    @endif
                </div>
            @endif
        </div>

        </div>{{-- /tab:config group 1 --}}

        <div x-show="tab === 'info'" class="space-y-4">
            <h3 class="text-lg font-semibold text-primary">Registration Fields</h3>
            <p class="text-sm text-muted">Choose which fields shooters must fill in when registering for this match.</p>

            <div class="space-y-3">
                @foreach(['rifle' => 'Rifle Selection', 'ammo' => 'Ammo Selection', 'division' => 'Division', 'category' => 'Category', 'emergency_contact' => 'Emergency Contact'] as $field => $label)
                <div class="flex items-center justify-between rounded-lg border border-border bg-surface-2 px-4 py-3">
                    <span class="text-sm font-medium text-secondary">{{ $label }}</span>
                    <select wire:model="registrationFieldsConfig.{{ $field }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-primary">
                        <option value="required">Required</option>
                        <option value="optional">Optional</option>
                        <option value="hidden">Hidden</option>
                    </select>
                </div>
                @endforeach
            </div>
        </div>{{-- /tab:info (registration fields) --}}

        <div x-show="tab === 'config'" x-cloak class="space-y-6">

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

        </div>{{-- /tab:config group 2 --}}

        <div x-show="tab === 'stages'" x-cloak class="space-y-6">

        {{-- Target Sets — Relay scoring (also the fallback when scoring_type is an
             unknown legacy value like 'royal_flush' — don't leave the tab blank) --}}
        @if(! in_array($scoring_type, ['prs', 'elr'], true))
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
                            @if($matchDays > 1)
                            <select wire:change="updateTargetSet({{ $ts->id }}, 'match_day', $event.target.value)"
                                    class="rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-sm text-primary">
                                @for($d = 1; $d <= $matchDays; $d++)
                                    <option value="{{ $d }}" {{ ($ts->match_day ?? 1) == $d ? 'selected' : '' }}>Day {{ $d }}</option>
                                @endfor
                            </select>
                            @endif
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

            <div class="rounded-xl border border-dashed border-border bg-surface/50 p-4 space-y-3" x-data="{ dist: '' }">
                <h3 class="text-sm font-medium text-secondary">Add Target Set</h3>
                <div class="flex gap-3 items-end flex-wrap">
                    <div class="w-32">
                        <label class="block text-xs font-medium text-secondary mb-1">Distance (m)</label>
                        <input x-model="dist" type="number" min="1" placeholder="e.g. 400"
                               @keydown.enter.prevent="$wire.addTargetSetValue(dist); dist = ''"
                               class="w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500" />
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="$wire.addTargetSetValue(dist); dist = ''"
                                class="rounded-md bg-accent px-3 py-2 text-sm font-medium text-white hover:bg-accent-hover">
                            Add Target Set
                        </button>
                        {{-- Quick presets: Royal Flush 400/500/600/700 in one click. --}}
                        @if($match?->organization?->isRoyalFlushOrg())
                            <button type="button" wire:click="addRoyalFlushPresets"
                                    class="rounded-md border border-border bg-surface-2 px-3 py-2 text-sm font-medium text-secondary hover:bg-surface">
                                + RF Presets (400/500/600/700)
                            </button>
                        @endif
                    </div>
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
                                   class="w-64 rounded-md border border-border bg-surface-2 px-3 py-1 text-sm font-semibold text-primary focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
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
                            @if($matchDays > 1)
                            <select wire:change="updateTargetSet({{ $ts->id }}, 'match_day', $event.target.value)"
                                    class="rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-sm text-primary">
                                @for($d = 1; $d <= $matchDays; $d++)
                                    <option value="{{ $d }}" {{ ($ts->match_day ?? 1) == $d ? 'selected' : '' }}>Day {{ $d }}</option>
                                @endfor
                            </select>
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
                                   class="w-20 rounded-md border border-border bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                   wire:change="updateTargetSet({{ $ts->id }}, 'total_shots', $event.target.value)" />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-muted whitespace-nowrap">Par Time (s):</label>
                            <input type="number" value="{{ $ts->par_time_seconds }}" step="0.01" min="0" placeholder="e.g. 105"
                                   class="w-24 rounded-md border border-border bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                                   wire:change="updateParTime({{ $ts->id }}, $event.target.value)" />
                        </div>
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
                    {{-- Section A: Targets (Physical Plates) --}}
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
                                                    <input type="number" value="{{ $gong->target_size_mm }}" step="0.01" min="0.01" placeholder="mm"
                                                           class="w-24 rounded border border-border bg-surface-2 px-2 py-1 text-sm text-primary text-center focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
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

                    {{-- Section C: Shot Sequence (Matrix + Ordered List) --}}
                    @if($ts->positions->isNotEmpty() && $ts->gongs->isNotEmpty())
                    <div class="border-t border-border p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-muted">
                                Shot Sequence
                                <span class="ml-2 text-amber-400 font-mono normal-case">({{ $ts->shotSequence->count() }} shots)</span>
                            </h4>
                            <div class="flex gap-2">
                                <flux:button size="xs" variant="ghost" wire:click="autoFillSequence({{ $ts->id }})"
                                             wire:confirm="Generate all position × target combos? This replaces the current sequence.">Auto-fill</flux:button>
                                @if($ts->shotSequence->isNotEmpty())
                                    <flux:button size="xs" variant="ghost" class="!text-accent" wire:click="clearSequence({{ $ts->id }})"
                                                 wire:confirm="Clear the entire shot sequence?">Clear</flux:button>
                                @endif
                            </div>
                        </div>

                        {{-- Matrix grid: positions as rows, targets as columns --}}
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
                        <flux:input wire:model.live.debounce.300ms="tsLabel" label="Stage Name" placeholder="e.g. Stage 1 — Positional" />
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
                        @if($matchDays > 1)
                        <select wire:change="updateElrStageDay({{ $stage->id }}, $event.target.value)"
                                class="rounded-lg border border-border bg-surface-2 px-3 py-1.5 text-sm text-primary">
                            @for($d = 1; $d <= $matchDays; $d++)
                                <option value="{{ $d }}" {{ ($stage->match_day ?? 1) == $d ? 'selected' : '' }}>Day {{ $d }}</option>
                            @endfor
                        </select>
                        @endif
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

        </div>{{-- /tab:stages --}}

        <div x-show="tab === 'config'" x-cloak class="space-y-6">

        {{-- Disqualifications --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-primary">Disqualifications</h2>
                    @if($disqualifications->isNotEmpty())
                        <span class="rounded-full bg-red-600/20 px-2 py-0.5 text-xs font-bold text-red-400">{{ $disqualifications->count() }}</span>
                    @endif
                </div>
                @if($squads->flatMap(fn($s) => $s->shooters)->isNotEmpty())
                    <div x-data="{ open: false, type: 'match', shooterId: null, stageId: null }" class="relative">
                        <button @click="open = !open" class="rounded-lg bg-red-600/20 px-3 py-1.5 text-xs font-semibold text-red-400 hover:bg-red-600/30 transition-colors">
                            + Issue DQ
                        </button>
                        <div x-show="open" x-transition @click.away="open = false" class="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-border bg-surface-2 p-4 shadow-xl space-y-3">
                            <div>
                                <label class="text-xs font-medium text-muted">DQ Type</label>
                                <select x-model="type" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-primary">
                                    <option value="match">Match DQ (full disqualification)</option>
                                    <option value="stage">Stage DQ (single stage)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-muted">Shooter</label>
                                <select x-model="shooterId" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-primary">
                                    <option value="">Select shooter...</option>
                                    @foreach($squads as $squad)
                                        @foreach($squad->shooters->where('status', '!=', 'dq') as $sh)
                                            <option value="{{ $sh->id }}">{{ $sh->name }} ({{ $squad->name }})</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="type === 'stage'">
                                <label class="text-xs font-medium text-muted">Stage</label>
                                <select x-model="stageId" class="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-primary">
                                    <option value="">Select stage...</option>
                                    @foreach($targetSets as $ts)
                                        <option value="{{ $ts->id }}">{{ $ts->label ?: 'Stage '.$ts->sort_order }} {{ $ts->distance_meters ? "— {$ts->distance_meters}m" : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button
                                @click="if(shooterId) { $wire.openDqModal(parseInt(shooterId), type === 'stage' && stageId ? parseInt(stageId) : null); open = false; }"
                                class="w-full rounded-lg bg-red-600 px-3 py-2 text-sm font-bold text-white hover:bg-red-700 transition-colors"
                            >Continue — Enter Reason</button>
                        </div>
                    </div>
                @endif
            </div>

            @if($disqualifications->isNotEmpty())
                <div class="space-y-2">
                    @foreach($disqualifications as $dq)
                        <div class="flex items-center justify-between rounded-lg border border-red-600/30 bg-red-900/10 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-red-600/30 px-1.5 py-0.5 text-[10px] font-bold text-red-400">{{ $dq->isMatchDq() ? 'MATCH DQ' : 'STAGE DQ' }}</span>
                                    <span class="text-sm font-semibold text-primary">{{ $dq->shooter?->name ?? 'Unknown' }}</span>
                                    @if($dq->targetSet)
                                        <span class="text-xs text-muted">{{ $dq->targetSet->label ?: "Stage {$dq->targetSet->stage_number}" }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-muted">{{ $dq->reason }}</p>
                                <p class="mt-0.5 text-[10px] text-muted/60">By {{ $dq->issuedBy?->name ?? 'Unknown' }} &middot; {{ $dq->created_at->diffForHumans() }}</p>
                            </div>
                            <button wire:click="revokeDq({{ $dq->id }})" wire:confirm="Revoke this DQ for {{ $dq->shooter?->name }}?" class="ml-3 rounded-lg border border-border px-3 py-1.5 text-xs text-muted hover:border-green-500 hover:text-green-400 transition-colors">
                                Revoke
                            </button>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-muted">No disqualifications issued.</p>
            @endif
        </div>

        {{-- DQ Reason Modal --}}
        @if($showDqModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="$set('showDqModal', false)">
                <div class="w-full max-w-md rounded-xl border border-border bg-surface p-6 shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-primary">
                        @if($dqTargetSetId)
                            Stage Disqualification
                        @else
                            Match Disqualification
                        @endif
                    </h3>
                    @php
                        $dqShooter = $dqShooterId ? \App\Models\Shooter::find($dqShooterId) : null;
                        $dqStage = $dqTargetSetId ? \App\Models\TargetSet::find($dqTargetSetId) : null;
                    @endphp
                    <p class="text-sm text-muted">
                        Disqualifying <span class="font-semibold text-primary">{{ $dqShooter?->name ?? 'Unknown' }}</span>
                        @if($dqStage)
                            from <span class="font-semibold text-primary">{{ $dqStage->label ?: "Stage {$dqStage->stage_number}" }}</span>
                        @else
                            from the <span class="font-semibold text-red-400">entire match</span>
                        @endif
                    </p>
                    <div>
                        <label class="text-xs font-medium text-muted">Reason (required)</label>
                        <textarea wire:model="dqReason" rows="3" placeholder="Describe the offence..." class="mt-1 w-full rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary placeholder-muted/40 focus:border-red-500 focus:ring-red-500"></textarea>
                        @error('dqReason') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-3">
                        <button wire:click="$set('showDqModal', false)" class="flex-1 rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-muted hover:bg-surface-2 transition-colors">Cancel</button>
                        <button wire:click="issueDq" class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-700 transition-colors">Confirm DQ</button>
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

            @if($scoring_type === 'standard')
                <div class="rounded-lg border border-dashed border-amber-600/40 bg-amber-950/10 p-4 space-y-3">
                    <div>
                        <h3 class="text-sm font-semibold text-amber-200">Equipment sheet import (Royal Flush)</h3>
                        <p class="mt-1 text-xs text-muted">
                            Paste <strong>tab-separated</strong> rows from Excel or Google Sheets. Expected columns (16):
                            timestamp, name, caliber, bullet, bullet weight, action, barrel, trigger, chassis, muzzle, scope, mount, bipod, phone, SA ID, notes/share rifle.
                            Cells like <code class="text-[10px] text-amber-300/80">8.43E+08</code> are expanded to full numbers. Creates placeholder accounts
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

        {{-- Custom Registration Fields --}}
        <flux:separator />

        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-primary">Custom Registration Fields</h2>
                <p class="mt-1 text-xs text-muted">Add extra fields to the registration form. Shooters fill these in when signing up.</p>
            </div>

            {{-- Existing fields list --}}
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

            {{-- Add / Edit field form --}}
            <div class="rounded-lg border border-dashed border-border bg-surface-2/20 p-4 space-y-3">
                <h3 class="text-sm font-semibold text-secondary">{{ $editingCustomFieldId ? 'Edit Field' : 'Add Field' }}</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-muted mb-1">Label *</label>
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
                $sideBetLocked = $match->status === MatchStatus::Completed;
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
                                Buy-in is locked after the match is completed. {{ count($sideBetShooterIds) }} {{ Str::plural('participant', count($sideBetShooterIds)) }} registered.
                            @else
                                Tick shooters who bought in. You can keep adding / removing until the match is completed.
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

        <flux:separator />

        {{-- Message Shooters --}}
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-primary">Message Shooters</h2>
            <p class="text-xs text-muted">Send an announcement to registered shooters. They'll receive a notification.</p>

            <div class="space-y-3">
                <flux:input wire:model="msgSubject" label="Subject" placeholder="e.g. Range update, schedule change…" />

                <flux:textarea wire:model="msgBody" label="Body" rows="4" placeholder="Type your message…" />

                <flux:select wire:model="msgAudience" label="Audience">
                    <option value="all">All Registrants</option>
                    <option value="confirmed">Confirmed Only</option>
                </flux:select>

                <div class="flex justify-end">
                    <flux:button wire:click="sendMessage" variant="primary" size="sm">Send Message</flux:button>
                </div>
            </div>

            @if($messages->isNotEmpty())
                <div class="mt-4 space-y-2">
                    <h3 class="text-sm font-medium text-secondary">Message History</h3>
                    @foreach($messages as $msg)
                        <div class="rounded-lg border border-border bg-surface-2/30 p-3 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-primary">{{ $msg->subject }}</span>
                                <span class="rounded-full bg-accent/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-accent">{{ $msg->audience }}</span>
                            </div>
                            <p class="text-xs text-muted line-clamp-2">{{ $msg->body }}</p>
                            <p class="text-[10px] text-muted">
                                Sent {{ $msg->sent_at->diffForHumans() }} by {{ $msg->sender?->name ?? 'Unknown' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        </div>{{-- /tab:config group 3 --}}
    @endif
</div>

@once
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
@endonce
