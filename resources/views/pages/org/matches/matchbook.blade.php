<?php

use App\Models\MatchBook;
use App\Models\MatchBookLocation;
use App\Models\Organization;
use App\Models\ShootingMatch;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')]
    #[Title('Edit Match Book')]
    class extends Component {
    use WithFileUploads;

    public Organization $organization;
    public ShootingMatch $match;
    public MatchBook $matchBook;
    public string $activeTab = 'content';

    public string $welcome_note = '';
    public string $custom_notes = '';
    public string $program = '';
    public string $procedures = '';
    public string $safety = '';
    public string $match_breakdown = '';
    public string $sponsor_acknowledgement = '';

    public string $primary_color = '';
    public string $secondary_color = '';
    public string $accent_color = '';
    public string $text_color = '';
    public string $highlight_color = '';
    public string $match_type = '';
    public string $status = 'draft';
    public $cover_image = null;
    public $federation_logo = null;
    public $club_logo = null;

    public string $venue = '';
    public string $gps_coordinates = '';
    public string $venue_maps_link = '';
    public string $range_maps_link = '';
    public string $hospital_maps_link = '';
    public string $directions = '';
    public string $match_director_name = '';
    public string $match_director_phone = '';
    public string $match_director_email = '';
    public string $emergency_hospital_name = '';
    public string $emergency_hospital_address = '';
    public string $emergency_phone = '';

    public array $locations = [];
    public bool $include_summary_cards = true;
    public bool $include_dope_card = false;
    public bool $include_score_sheet = true;
    public string $subtitle = '';

    public function mount(Organization $organization, ShootingMatch $match): void
    {
        $this->organization = $organization;
        $this->match = $match;

        $book = $match->matchBook;
        if (! $book) {
            $book = MatchBook::create(['match_id' => $match->id, 'status' => 'draft']);
        }
        $this->matchBook = $book->fresh();
        $this->hydrateFromMatchBook();
    }

    protected function hydrateFromMatchBook(): void
    {
        $b = $this->matchBook;
        $this->subtitle = (string) ($b->subtitle ?? '');
        $this->welcome_note = (string) ($b->welcome_note ?? '');
        $this->custom_notes = (string) ($b->custom_notes ?? '');
        $this->program = (string) ($b->program ?? '');
        $this->procedures = (string) ($b->procedures ?? '');
        $this->safety = (string) ($b->safety ?? '');
        $this->match_breakdown = (string) ($b->match_breakdown ?? '');
        $this->sponsor_acknowledgement = (string) ($b->sponsor_acknowledgement ?? '');
        $this->primary_color = (string) ($b->primary_color ?? '#1e3a5f');
        $this->secondary_color = (string) ($b->secondary_color ?? '');
        $this->accent_color = (string) ($b->accent_color ?? '');
        $this->text_color = (string) ($b->text_color ?? '');
        $this->highlight_color = (string) ($b->highlight_color ?? '');
        $this->match_type = (string) ($b->match_type ?? '');
        $this->status = (string) ($b->status ?? 'draft');
        $this->venue = (string) ($b->venue ?? '');
        $this->gps_coordinates = (string) ($b->gps_coordinates ?? '');
        $this->venue_maps_link = (string) ($b->venue_maps_link ?? '');
        $this->range_maps_link = (string) ($b->range_maps_link ?? '');
        $this->hospital_maps_link = (string) ($b->hospital_maps_link ?? '');
        $this->directions = (string) ($b->directions ?? '');
        $this->match_director_name = (string) ($b->match_director_name ?? '');
        $this->match_director_phone = (string) ($b->match_director_phone ?? '');
        $this->match_director_email = (string) ($b->match_director_email ?? '');
        $this->emergency_hospital_name = (string) ($b->emergency_hospital_name ?? '');
        $this->emergency_hospital_address = (string) ($b->emergency_hospital_address ?? '');
        $this->emergency_phone = (string) ($b->emergency_phone ?? '');
        $this->include_summary_cards = (bool) $b->include_summary_cards;
        $this->include_dope_card = (bool) $b->include_dope_card;
        $this->include_score_sheet = (bool) $b->include_score_sheet;
        $this->locations = $b->locations()->orderBy('display_order')->get()->map(fn ($l) => [
            'id' => $l->id, 'name' => $l->name, 'maps_link' => (string) ($l->maps_link ?? ''), 'gps_coordinates' => (string) ($l->gps_coordinates ?? ''),
        ])->values()->all();
    }

    public function addLocation(): void
    {
        $this->locations[] = ['id' => null, 'name' => '', 'maps_link' => '', 'gps_coordinates' => ''];
    }

    public function removeLocation(int $index): void
    {
        unset($this->locations[$index]);
        $this->locations = array_values($this->locations);
    }

    public function save(): void
    {
        $this->validate([
            'subtitle' => 'nullable|string|max:255',
            'welcome_note' => 'nullable|string|max:65000',
            'custom_notes' => 'nullable|string|max:65000',
            'program' => 'nullable|string|max:65000',
            'procedures' => 'nullable|string|max:65000',
            'safety' => 'nullable|string|max:65000',
            'match_breakdown' => 'nullable|string|max:65000',
            'sponsor_acknowledgement' => 'nullable|string|max:65000',
            'primary_color' => 'nullable|string|max:32',
            'match_type' => 'nullable|in:centerfire,rimfire',
            'status' => 'required|in:draft,ready,published',
            'cover_image' => 'nullable|image|max:8192',
            'federation_logo' => 'nullable|image|max:4096',
            'club_logo' => 'nullable|image|max:4096',
            'locations.*.name' => 'nullable|string|max:255',
        ]);

        $book = $this->matchBook->fresh();
        $disk = 'public';
        $dir = 'match-books/' . $book->id;

        if ($this->cover_image) {
            if ($book->cover_image_path) Storage::disk($disk)->delete($book->cover_image_path);
            $book->cover_image_path = $this->cover_image->store($dir . '/cover', $disk);
        }
        if ($this->federation_logo) {
            if ($book->federation_logo_path) Storage::disk($disk)->delete($book->federation_logo_path);
            $book->federation_logo_path = $this->federation_logo->store($dir . '/logos', $disk);
        }
        if ($this->club_logo) {
            if ($book->club_logo_path) Storage::disk($disk)->delete($book->club_logo_path);
            $book->club_logo_path = $this->club_logo->store($dir . '/logos', $disk);
        }

        $nullIfEmpty = fn ($v) => $v !== '' ? $v : null;

        $book->fill([
            'subtitle' => $nullIfEmpty($this->subtitle),
            'welcome_note' => $nullIfEmpty($this->welcome_note),
            'custom_notes' => $nullIfEmpty($this->custom_notes),
            'program' => $nullIfEmpty($this->program),
            'procedures' => $nullIfEmpty($this->procedures),
            'safety' => $nullIfEmpty($this->safety),
            'match_breakdown' => $nullIfEmpty($this->match_breakdown),
            'sponsor_acknowledgement' => $nullIfEmpty($this->sponsor_acknowledgement),
            'primary_color' => $nullIfEmpty($this->primary_color),
            'secondary_color' => $nullIfEmpty($this->secondary_color),
            'accent_color' => $nullIfEmpty($this->accent_color),
            'text_color' => $nullIfEmpty($this->text_color),
            'highlight_color' => $nullIfEmpty($this->highlight_color),
            'match_type' => $nullIfEmpty($this->match_type),
            'status' => $this->status,
            'venue' => $nullIfEmpty($this->venue),
            'gps_coordinates' => $nullIfEmpty($this->gps_coordinates),
            'venue_maps_link' => $nullIfEmpty($this->venue_maps_link),
            'range_maps_link' => $nullIfEmpty($this->range_maps_link),
            'hospital_maps_link' => $nullIfEmpty($this->hospital_maps_link),
            'directions' => $nullIfEmpty($this->directions),
            'match_director_name' => $nullIfEmpty($this->match_director_name),
            'match_director_phone' => $nullIfEmpty($this->match_director_phone),
            'match_director_email' => $nullIfEmpty($this->match_director_email),
            'emergency_hospital_name' => $nullIfEmpty($this->emergency_hospital_name),
            'emergency_hospital_address' => $nullIfEmpty($this->emergency_hospital_address),
            'emergency_phone' => $nullIfEmpty($this->emergency_phone),
            'include_summary_cards' => $this->include_summary_cards,
            'include_dope_card' => $this->include_dope_card,
            'include_score_sheet' => $this->include_score_sheet,
        ]);
        $book->save();

        $filtered = array_values(array_filter($this->locations, fn ($r) => trim($r['name'] ?? '') !== ''));
        $keptIds = [];
        foreach ($filtered as $order => $row) {
            $payload = ['name' => trim($row['name']), 'maps_link' => trim($row['maps_link'] ?? '') ?: null, 'gps_coordinates' => trim($row['gps_coordinates'] ?? '') ?: null, 'display_order' => $order];
            if (!empty($row['id'])) {
                $loc = MatchBookLocation::where('match_book_id', $book->id)->whereKey($row['id'])->first();
                if ($loc) { $loc->update($payload); $keptIds[] = $loc->id; }
            } else {
                $loc = $book->locations()->create($payload);
                $keptIds[] = $loc->id;
            }
        }
        $book->locations()->whereNotIn('id', $keptIds)->delete();

        $this->cover_image = null;
        $this->federation_logo = null;
        $this->club_logo = null;
        $this->matchBook = $book->fresh();
        $this->hydrateFromMatchBook();
        Flux::toast('Match book saved.', variant: 'success');
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Match Book</flux:heading>
            <p class="mt-1 text-sm text-muted">{{ $match->name }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button variant="primary" wire:click="save">Save</flux:button>
            <flux:button variant="ghost" href="{{ route('org.matches.matchbook.preview', ['organization' => $organization, 'match' => $match]) }}" target="_blank">Preview</flux:button>
            <flux:button variant="ghost" href="{{ route('org.matches.matchbook.download', ['organization' => $organization, 'match' => $match]) }}" target="_blank">Download PDF</flux:button>
        </div>
    </div>

    <flux:tab.group>
        <flux:tabs wire:model.live="activeTab">
            <flux:tab name="content">Content</flux:tab>
            <flux:tab name="branding">Branding</flux:tab>
            <flux:tab name="venue">Venue</flux:tab>
            <flux:tab name="locations">Locations</flux:tab>
            <flux:tab name="stages">Stages</flux:tab>
            <flux:tab name="options">Options</flux:tab>
        </flux:tabs>

        <flux:tab.panel name="content" class="mt-6 space-y-6">
            <flux:textarea wire:model="welcome_note" label="Welcome note" rows="5" />
            <flux:textarea wire:model="custom_notes" label="Custom notes" rows="5" />
            <flux:textarea wire:model="program" label="Program" rows="6" />
            <flux:textarea wire:model="procedures" label="Procedures" rows="6" />
            <flux:textarea wire:model="safety" label="Safety" rows="6" />
            <flux:textarea wire:model="match_breakdown" label="Match breakdown" rows="6" />
            <flux:textarea wire:model="sponsor_acknowledgement" label="Sponsor acknowledgement" rows="4" />
        </flux:tab.panel>

        <flux:tab.panel name="branding" class="mt-6 space-y-6">
            <flux:input type="file" wire:model="cover_image" label="Cover image" />
            @if($matchBook->cover_image_path) <p class="text-sm text-muted">Current: <a class="text-amber-600 underline" href="{{ Storage::url($matchBook->cover_image_path) }}" target="_blank">view</a></p> @endif
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="primary_color" label="Primary color" placeholder="#1e3a5f" />
                <flux:input wire:model="secondary_color" label="Secondary color" />
                <flux:input wire:model="accent_color" label="Accent color" />
                <flux:input wire:model="text_color" label="Text color" />
                <flux:input wire:model="highlight_color" label="Highlight color" />
            </div>
            <flux:input type="file" wire:model="federation_logo" label="Federation logo" />
            <flux:input type="file" wire:model="club_logo" label="Club logo" />
            <flux:select wire:model="match_type" label="Match type"><flux:option value="">Default</flux:option><flux:option value="centerfire">Centerfire</flux:option><flux:option value="rimfire">Rimfire</flux:option></flux:select>
            <flux:select wire:model="status" label="Status"><flux:option value="draft">Draft</flux:option><flux:option value="ready">Ready</flux:option><flux:option value="published">Published</flux:option></flux:select>
        </flux:tab.panel>

        <flux:tab.panel name="venue" class="mt-6 space-y-6">
            <flux:input wire:model="venue" label="Venue" />
            <flux:input wire:model="gps_coordinates" label="GPS coordinates" />
            <flux:input wire:model="venue_maps_link" label="Venue maps link" />
            <flux:input wire:model="range_maps_link" label="Range maps link" />
            <flux:input wire:model="hospital_maps_link" label="Hospital maps link" />
            <flux:textarea wire:model="directions" label="Directions" rows="5" />
            <flux:heading size="lg">Match Director</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="match_director_name" label="Name" />
                <flux:input wire:model="match_director_phone" label="Phone" />
                <flux:input wire:model="match_director_email" label="Email" type="email" />
            </div>
            <flux:heading size="lg">Emergency</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="emergency_hospital_name" label="Hospital name" />
                <flux:input wire:model="emergency_phone" label="Emergency phone" />
                <flux:textarea wire:model="emergency_hospital_address" label="Hospital address" rows="3" />
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="locations" class="mt-6 space-y-4">
            <div class="flex justify-end"><flux:button variant="ghost" size="sm" wire:click="addLocation">Add location</flux:button></div>
            @forelse($locations as $index => $loc)
                <div wire:key="loc-{{ $index }}" class="rounded-xl border border-border p-4">
                    <div class="mb-3 flex items-center justify-between"><span class="text-sm font-medium">Location {{ $index + 1 }}</span><flux:button variant="ghost" size="sm" wire:click="removeLocation({{ $index }})">Remove</flux:button></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="locations.{{ $index }}.name" label="Name" />
                        <flux:input wire:model="locations.{{ $index }}.gps_coordinates" label="GPS" />
                        <flux:input wire:model="locations.{{ $index }}.maps_link" label="Maps link" class="sm:col-span-2" />
                    </div>
                </div>
            @empty
                <p class="text-sm text-muted">No locations yet.</p>
            @endforelse
        </flux:tab.panel>

        <flux:tab.panel name="stages" class="mt-6">
            <livewire:matchbook-stage-editor :match-book-id="$matchBook->id" />
        </flux:tab.panel>

        <flux:tab.panel name="options" class="mt-6 space-y-6">
            <flux:input wire:model="subtitle" label="Subtitle" />
            <flux:checkbox wire:model="include_summary_cards" label="Include summary cards" />
            <flux:checkbox wire:model="include_dope_card" label="Include dope card" />
            <flux:checkbox wire:model="include_score_sheet" label="Include score sheet" />
        </flux:tab.panel>
    </flux:tab.group>
</div>
