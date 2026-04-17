<?php

use App\Models\MatchRegistration;
use App\Models\ShootingMatch;
use App\Models\Squad;
use App\Models\Shooter;
use App\Enums\MatchStatus;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Registrations')]
    class extends Component {
    public string $filter = 'proof_submitted';
    public string $matchFilter = '';

    public ?int $expandedRegId = null;

    public function approve(int $id): void
    {
        $reg = MatchRegistration::findOrFail($id);
        $reg->update(['payment_status' => 'confirmed']);

        $match = $reg->match;
        $squad = $match->squads()->firstOrCreate(
            ['name' => 'Default'],
            ['sort_order' => 0]
        );

        $maxSort = $squad->shooters()->max('sort_order') ?? 0;

        Shooter::create([
            'squad_id' => $squad->id,
            'name' => $reg->user->name,
            'user_id' => $reg->user_id,
            'sort_order' => $maxSort + 1,
        ]);

        Flux::toast('Registration approved. Shooter added to match.', variant: 'success');
    }

    public function approveFreeEntry(int $id): void
    {
        $reg = MatchRegistration::findOrFail($id);
        $reg->update(['payment_status' => 'confirmed', 'is_free_entry' => true, 'amount' => 0]);

        $match = $reg->match;
        $squad = $match->squads()->firstOrCreate(['name' => 'Default'], ['sort_order' => 0]);
        $maxSort = $squad->shooters()->max('sort_order') ?? 0;

        Shooter::create([
            'squad_id' => $squad->id,
            'name' => $reg->user->name,
            'user_id' => $reg->user_id,
            'sort_order' => $maxSort + 1,
        ]);

        Flux::toast('Free entry approved. Shooter added to match.', variant: 'success');
    }

    public function reject(int $id): void
    {
        $reg = MatchRegistration::findOrFail($id);
        $reg->update(['payment_status' => 'rejected']);

        Flux::toast('Registration rejected.', variant: 'warning');
    }

    public function toggleDetails(int $id): void
    {
        $this->expandedRegId = $this->expandedRegId === $id ? null : $id;
    }

    public function with(): array
    {
        $registrations = MatchRegistration::with(['user', 'match'])
            ->when($this->filter !== 'all', fn ($q) => $q->where('payment_status', $this->filter))
            ->when($this->matchFilter !== '', fn ($q) => $q->where('match_id', $this->matchFilter))
            ->latest()
            ->get();

        $upcomingStatuses = [
            MatchStatus::PreRegistration->value,
            MatchStatus::RegistrationOpen->value,
            MatchStatus::RegistrationClosed->value,
            MatchStatus::SquaddingOpen->value,
            MatchStatus::SquaddingClosed->value,
            MatchStatus::Ready->value,
            MatchStatus::Active->value,
        ];

        $matchesWithRegs = ShootingMatch::whereHas('registrations')
            ->orderByRaw("FIELD(status, 'registration_open', 'pre_registration', 'squadding_open', 'active', 'registration_closed', 'draft', 'completed') ASC")
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($m) => (object) [
                'id' => $m->id,
                'name' => $m->name,
                'date' => $m->date?->format('d M Y'),
                'status' => $m->status,
                'is_upcoming' => in_array($m->status->value, $upcomingStatuses),
                'reg_count' => $m->registrations()->count(),
            ]);

        return [
            'registrations' => $registrations,
            'matchesWithRegs' => $matchesWithRegs,
        ];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Match Registrations</flux:heading>
        <p class="mt-1 text-sm text-muted">Review and approve member registrations.</p>
    </div>

    {{-- Filters --}}
    <div class="space-y-3">
        {{-- Match filter --}}
        <div>
            <label class="block text-xs font-medium text-muted mb-1">Filter by Match</label>
            <select wire:model.live="matchFilter"
                    class="w-full max-w-sm rounded-lg border border-border bg-surface-2 px-3 py-2 text-sm text-primary focus:border-red-500 focus:ring-1 focus:ring-red-500">
                <option value="">All Matches</option>
                @if($matchesWithRegs->where('is_upcoming', true)->isNotEmpty())
                    <optgroup label="Upcoming / Active">
                        @foreach($matchesWithRegs->where('is_upcoming', true) as $m)
                            <option value="{{ $m->id }}">{{ $m->name }} — {{ $m->date ?? 'No date' }} ({{ $m->reg_count }}) [{{ $m->status->label() }}]</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($matchesWithRegs->where('is_upcoming', false)->isNotEmpty())
                    <optgroup label="Past / Completed">
                        @foreach($matchesWithRegs->where('is_upcoming', false) as $m)
                            <option value="{{ $m->id }}">{{ $m->name }} — {{ $m->date ?? 'No date' }} ({{ $m->reg_count }})</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
        </div>

        {{-- Status filter --}}
        <div class="flex flex-wrap gap-2">
            @foreach(['proof_submitted' => 'Pending Review', 'pending_payment' => 'Awaiting Payment', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
                <button wire:click="$set('filter', '{{ $value }}')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ $filter === $value ? 'bg-accent text-primary' : 'bg-surface-2 text-secondary hover:bg-surface-2' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        @if($registrations->isEmpty())
            <div class="px-6 py-12 text-center">
                <p class="text-muted">No registrations matching this filter.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-muted">
                            <th class="px-6 py-3 font-medium">Member</th>
                            <th class="px-6 py-3 font-medium">Match</th>
                            <th class="px-6 py-3 font-medium">Reference</th>
                            <th class="px-6 py-3 font-medium">Amount</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium">POP</th>
                            <th class="px-6 py-3 font-medium">Date</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        @foreach($registrations as $reg)
                            <tr class="hover:bg-surface-2/30 transition-colors cursor-pointer" wire:key="reg-{{ $reg->id }}" wire:click="toggleDetails({{ $reg->id }})">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('shooter.profile', $reg->user_id) }}" class="font-medium text-primary hover:underline" onclick="event.stopPropagation()">
                                            {{ $reg->user->name }}
                                        </a>
                                        @if($reg->share_rifle_with)
                                            <span class="rounded bg-amber-600/20 px-1.5 py-0.5 text-[10px] font-medium text-amber-400">Shares rifle</span>
                                        @endif
                                    </div>
                                    <div class="mt-1">
                                        <x-badge-flair :userId="$reg->user_id" :limit="4" />
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-secondary">{{ $reg->match->name }}</td>
                                <td class="px-6 py-3 font-mono text-xs text-muted">{{ $reg->payment_reference }}</td>
                                <td class="px-6 py-3 text-secondary">{{ $reg->amount ? 'R'.number_format($reg->amount, 2) : 'Free' }}</td>
                                <td class="px-6 py-3">
                                    @switch($reg->payment_status)
                                        @case('pending_payment')
                                            <flux:badge size="sm" color="zinc">Awaiting Payment</flux:badge>
                                            @break
                                        @case('proof_submitted')
                                            <flux:badge size="sm" color="amber">Pending Review</flux:badge>
                                            @break
                                        @case('confirmed')
                                            <flux:badge size="sm" color="green">Confirmed</flux:badge>
                                            @break
                                        @case('rejected')
                                            <flux:badge size="sm" color="red">Rejected</flux:badge>
                                            @break
                                    @endswitch
                                </td>
                                <td class="px-6 py-3">
                                    @if($reg->proof_of_payment_path)
                                        <a href="{{ Storage::url($reg->proof_of_payment_path) }}" target="_blank"
                                           class="text-accent hover:text-accent text-xs font-medium" onclick="event.stopPropagation()">
                                            View POP
                                        </a>
                                    @else
                                        <span class="text-muted text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-muted text-xs">{{ $reg->created_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-3 text-right" onclick="event.stopPropagation()">
                                    @if($reg->payment_status === 'proof_submitted')
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button size="sm" variant="primary" class="!bg-green-600 hover:!bg-green-700"
                                                         wire:click="approve({{ $reg->id }})"
                                                         wire:confirm="Approve this registration? A shooter will be added to the match.">
                                                Approve
                                            </flux:button>
                                            <flux:button size="sm" variant="ghost" class="!text-accent hover:!text-accent"
                                                         wire:click="reject({{ $reg->id }})"
                                                         wire:confirm="Reject this registration?">
                                                Reject
                                            </flux:button>
                                        </div>
                                    @endif
                                    @if($reg->payment_status === 'pending_payment' || $reg->payment_status === 'proof_submitted')
                                        <flux:button size="sm" variant="ghost" class="!text-emerald-400 hover:!text-emerald-300 mt-1"
                                                     wire:click="approveFreeEntry({{ $reg->id }})"
                                                     wire:confirm="Mark as free entry and approve? Payment will be waived.">
                                            Free Entry
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                            @if($expandedRegId === $reg->id && $reg->caliber)
                                <tr wire:key="reg-detail-{{ $reg->id }}">
                                    <td colspan="8" class="px-6 py-4 bg-surface-2/20">
                                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-xs sm:grid-cols-4">
                                            @if($reg->caliber)<div><span class="text-muted">Caliber:</span> <span class="text-secondary">{{ $reg->caliber }}</span></div>@endif
                                            @if($reg->bullet_brand_type)<div><span class="text-muted">Bullet:</span> <span class="text-secondary">{{ $reg->bullet_brand_type }}</span></div>@endif
                                            @if($reg->bullet_weight)<div><span class="text-muted">Weight:</span> <span class="text-secondary">{{ $reg->bullet_weight }}</span></div>@endif
                                            @if($reg->action_brand)<div><span class="text-muted">Action:</span> <span class="text-secondary">{{ $reg->action_brand }}</span></div>@endif
                                            @if($reg->barrel_brand_length)<div><span class="text-muted">Barrel:</span> <span class="text-secondary">{{ $reg->barrel_brand_length }}</span></div>@endif
                                            @if($reg->trigger_brand)<div><span class="text-muted">Trigger:</span> <span class="text-secondary">{{ $reg->trigger_brand }}</span></div>@endif
                                            @if($reg->stock_chassis_brand)<div><span class="text-muted">Stock/Chassis:</span> <span class="text-secondary">{{ $reg->stock_chassis_brand }}</span></div>@endif
                                            @if($reg->muzzle_brake_silencer_brand)<div><span class="text-muted">Muzzle/Silencer:</span> <span class="text-secondary">{{ $reg->muzzle_brake_silencer_brand }}</span></div>@endif
                                            @if($reg->scope_brand_type)<div><span class="text-muted">Scope:</span> <span class="text-secondary">{{ $reg->scope_brand_type }}</span></div>@endif
                                            @if($reg->scope_mount_brand)<div><span class="text-muted">Mount:</span> <span class="text-secondary">{{ $reg->scope_mount_brand }}</span></div>@endif
                                            @if($reg->bipod_brand)<div><span class="text-muted">Bipod:</span> <span class="text-secondary">{{ $reg->bipod_brand }}</span></div>@endif
                                            @if($reg->contact_number)<div><span class="text-muted">Contact:</span> <span class="text-secondary">{{ $reg->contact_number }}</span></div>@endif
                                            @if($reg->sa_id_number)<div><span class="text-muted">SA ID:</span> <span class="text-secondary">{{ $reg->sa_id_number }}</span></div>@endif
                                            @if($reg->share_rifle_with)<div class="col-span-2"><span class="text-amber-400 font-medium">Shares rifle with:</span> <span class="text-secondary">{{ $reg->share_rifle_with }}</span></div>@endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
