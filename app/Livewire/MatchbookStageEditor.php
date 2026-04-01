<?php

namespace App\Livewire;

use App\Models\MatchBook;
use App\Models\MatchBookShot;
use App\Models\MatchBookStage;
use App\Services\MatchBook\StageDifficultyService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MatchbookStageEditor extends Component
{
    public int $matchBookId;

    public ?int $editingStageId = null;

    public int $stage_number = 1;

    public string $name = '';

    public string $brief = '';

    public string $notes = '';

    public string $engagement_rules = '';

    public bool $compulsory_sequence = true;

    public bool $timed = false;

    public ?int $time_limit = null;

    public ?int $round_count = null;

    public ?int $positions_count = null;

    public int $movement_meters = 0;

    public string $sequence_display_format = 'blocks';

    public array $shots = [];

    public function mount(int $matchBookId): void
    {
        $this->matchBookId = $matchBookId;
    }

    #[Computed]
    public function matchBook(): MatchBook
    {
        return MatchBook::findOrFail($this->matchBookId);
    }

    #[Computed]
    public function difficultyPreview(): array
    {
        $service = app(StageDifficultyService::class);

        $stageData = [
            'time_limit' => (int) ($this->time_limit ?? 105),
            'round_count' => max(1, (int) ($this->round_count ?: max(1, count($this->shots)))),
            'positions_count' => (int) ($this->positions_count ?? 0),
            'movement_meters' => (int) $this->movement_meters,
        ];

        $shotsData = [];
        foreach ($this->shots as $row) {
            $shotsData[] = [
                'gong_label' => (string) ($row['gong_label'] ?? ''),
                'gong_name' => (string) ($row['gong_name'] ?? ''),
                'distance_m' => (float) ($row['distance_m'] ?? 0),
                'size_mm' => ($row['size_mm'] ?? '') !== '' ? (int) $row['size_mm'] : null,
                'position' => (int) ($row['position'] ?? 1),
            ];
        }

        return $service->calculateFromRaw($shotsData, $stageData, $this->matchBook->match_type ?? 'centerfire');
    }

    public function openCreateModal(): void
    {
        $this->editingStageId = null;
        $next = (int) (MatchBookStage::where('match_book_id', $this->matchBookId)->max('stage_number') ?? 0) + 1;
        $this->stage_number = $next;
        $this->name = 'Stage '.$next;
        $this->brief = '';
        $this->notes = '';
        $this->engagement_rules = '';
        $this->compulsory_sequence = true;
        $this->timed = false;
        $this->time_limit = 120;
        $this->round_count = 1;
        $this->positions_count = 1;
        $this->movement_meters = 0;
        $this->sequence_display_format = 'blocks';
        $this->shots = [$this->emptyShotRow(1)];
        Flux::modal('matchbook-stage-editor')->show();
    }

    public function openEditModal(int $stageId): void
    {
        $stage = MatchBookStage::where('match_book_id', $this->matchBookId)->with('shots')->findOrFail($stageId);
        $this->editingStageId = $stage->id;
        $this->stage_number = $stage->stage_number;
        $this->name = $stage->name;
        $this->brief = $stage->brief ?? '';
        $this->notes = $stage->notes ?? '';
        $this->engagement_rules = $stage->engagement_rules ?? '';
        $this->compulsory_sequence = (bool) $stage->compulsory_sequence;
        $this->timed = (bool) $stage->timed;
        $this->time_limit = $stage->time_limit;
        $this->round_count = $stage->round_count;
        $this->positions_count = $stage->positions_count;
        $this->movement_meters = (int) $stage->movement_meters;
        $this->sequence_display_format = $stage->sequence_display_format ?: 'blocks';
        $this->shots = $stage->shots->map(fn ($s) => [
            'id' => $s->id, 'shot_number' => $s->shot_number, 'position' => $s->position,
            'gong_label' => $s->gong_label, 'gong_name' => $s->gong_name ?? '',
            'distance_m' => (string) $s->distance_m, 'size_mm' => $s->size_mm !== null ? (string) $s->size_mm : '', 'shape' => $s->shape ?? '',
        ])->values()->all();
        if (empty($this->shots)) {
            $this->shots = [$this->emptyShotRow(1)];
        }
        Flux::modal('matchbook-stage-editor')->show();
    }

    public function closeStageModal(): void
    {
        $this->editingStageId = null;
        $this->shots = [];
        Flux::modal('matchbook-stage-editor')->close();
    }

    public function addShot(): void
    {
        $this->shots[] = $this->emptyShotRow(count($this->shots) + 1);
    }

    public function removeShot(int $index): void
    {
        unset($this->shots[$index]);
        $this->shots = array_values($this->shots);
        if (empty($this->shots)) {
            $this->shots = [$this->emptyShotRow(1)];
        }
    }

    public function saveStage(): void
    {
        $this->validate([
            'stage_number' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'shots' => 'required|array|min:1',
            'shots.*.position' => 'required|integer|min:1',
            'shots.*.gong_label' => 'required|string|max:255',
            'shots.*.distance_m' => 'required|numeric|min:0.01',
        ]);

        $payload = [
            'match_book_id' => $this->matchBookId,
            'stage_number' => $this->stage_number,
            'name' => $this->name,
            'brief' => $this->brief !== '' ? $this->brief : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'engagement_rules' => $this->engagement_rules !== '' ? $this->engagement_rules : null,
            'compulsory_sequence' => $this->compulsory_sequence,
            'timed' => $this->timed,
            'time_limit' => $this->timed ? $this->time_limit : null,
            'round_count' => $this->round_count,
            'positions_count' => $this->positions_count,
            'movement_meters' => $this->movement_meters,
            'sequence_display_format' => $this->sequence_display_format,
        ];

        DB::transaction(function () use ($payload) {
            $stage = $this->editingStageId
                ? tap(MatchBookStage::where('match_book_id', $this->matchBookId)->findOrFail($this->editingStageId), fn ($s) => $s->update($payload))
                : MatchBookStage::create($payload);

            $keptIds = [];
            foreach (array_values($this->shots) as $i => $row) {
                $data = [
                    'shot_number' => $i + 1,
                    'position' => (int) $row['position'],
                    'gong_label' => $row['gong_label'],
                    'gong_name' => $row['gong_name'] !== '' ? $row['gong_name'] : null,
                    'distance_m' => $row['distance_m'],
                    'size_mm' => ($row['size_mm'] ?? '') !== '' ? (int) $row['size_mm'] : null,
                    'shape' => ($row['shape'] ?? '') !== '' ? $row['shape'] : null,
                ];

                if (!empty($row['id'])) {
                    $shot = MatchBookShot::where('match_book_stage_id', $stage->id)->findOrFail($row['id']);
                    $shot->update($data);
                    $keptIds[] = $shot->id;
                } else {
                    $keptIds[] = $stage->shots()->create($data)->id;
                }
            }

            MatchBookShot::where('match_book_stage_id', $stage->id)->whereNotIn('id', $keptIds)->delete();
        });

        $this->closeStageModal();
        Flux::toast('Stage saved.', variant: 'success');
    }

    public function deleteStage(int $stageId): void
    {
        MatchBookStage::where('match_book_id', $this->matchBookId)->findOrFail($stageId)->delete();
        Flux::toast('Stage deleted.', variant: 'success');
    }

    protected function emptyShotRow(int $num): array
    {
        return ['id' => null, 'shot_number' => $num, 'position' => 1, 'gong_label' => '', 'gong_name' => '', 'distance_m' => '100', 'size_mm' => '', 'shape' => ''];
    }

    public static function shotMil(?string $sizeMm, ?string $distanceM): ?string
    {
        $d = (float) ($distanceM ?? 0);
        $s = (int) ($sizeMm ?? 0);
        return ($d > 0 && $s > 0) ? number_format($s / $d, 2) : null;
    }

    public static function shotMoa(?string $sizeMm, ?string $distanceM): ?string
    {
        $mil = self::shotMil($sizeMm, $distanceM);
        return $mil !== null ? number_format((float) $mil * 3.43775, 2) : null;
    }

    public function render(): View
    {
        return view('livewire.matchbook-stage-editor', [
            'stages' => MatchBookStage::where('match_book_id', $this->matchBookId)->withCount('shots')->orderBy('stage_number')->get(),
        ]);
    }
}
