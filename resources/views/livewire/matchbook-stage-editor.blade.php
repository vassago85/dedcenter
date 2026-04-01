<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="lg">Stages</flux:heading>
        <flux:button type="button" variant="primary" wire:click="openCreateModal">Add stage</flux:button>
    </div>

    @if($stages->isEmpty())
        <p class="rounded-xl border border-border bg-surface px-4 py-8 text-center text-sm text-muted">No stages yet.</p>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>#</flux:table.column>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Shots</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($stages as $stage)
                    <flux:table.row wire:key="stage-{{ $stage->id }}">
                        <flux:table.cell variant="strong">{{ $stage->stage_number }}</flux:table.cell>
                        <flux:table.cell>{{ $stage->name }}</flux:table.cell>
                        <flux:table.cell>{{ $stage->shots_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" wire:click="openEditModal({{ $stage->id }})">Edit</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="deleteStage({{ $stage->id }})" wire:confirm="Delete this stage and all its shots?">Delete</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="matchbook-stage-editor" class="min-w-[min(100vw-1rem,80rem)]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingStageId ? 'Edit Stage' : 'New Stage' }}</flux:heading>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <div class="space-y-4 lg:col-span-8">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input wire:model.live="stage_number" type="number" min="1" label="Stage number" />
                        <flux:input wire:model.live="name" label="Name" />
                    </div>
                    <flux:textarea wire:model.live="brief" label="Course of fire brief" rows="2" />
                    <flux:textarea wire:model.live="notes" label="Notes" rows="2" />
                    <flux:textarea wire:model.live="engagement_rules" label="Engagement rules (non-compulsory)" rows="2" />
                    <div class="flex flex-wrap gap-6">
                        <flux:checkbox wire:model.live="compulsory_sequence" label="Compulsory sequence" />
                        <flux:checkbox wire:model.live="timed" label="Timed" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input wire:model.live="time_limit" type="number" min="0" label="Time limit (s)" :disabled="!$timed" />
                        <flux:input wire:model.live="round_count" type="number" min="1" label="Rounds" />
                        <flux:input wire:model.live="positions_count" type="number" min="1" label="Positions" />
                        <flux:input wire:model.live="movement_meters" type="number" min="0" label="Movement (m)" />
                    </div>
                    <flux:select wire:model.live="sequence_display_format" label="Display format">
                        <option value="blocks">Shot blocks</option>
                        <option value="table">Table</option>
                    </flux:select>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <flux:heading size="md">Shots</flux:heading>
                            <flux:button size="sm" variant="ghost" wire:click="addShot">+ Add shot</flux:button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border text-left text-muted">
                                        <th class="px-2 py-2 w-10">#</th>
                                        <th class="px-2 py-2">Pos</th>
                                        <th class="px-2 py-2">Gong</th>
                                        <th class="px-2 py-2">Name</th>
                                        <th class="px-2 py-2">Dist (m)</th>
                                        <th class="px-2 py-2">Size (mm)</th>
                                        <th class="px-2 py-2">Shape</th>
                                        <th class="px-2 py-2">MIL</th>
                                        <th class="px-2 py-2">MOA</th>
                                        <th class="px-2 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($shots as $i => $shot)
                                        <tr wire:key="shot-{{ $i }}" class="border-b border-border/50">
                                            <td class="px-2 py-1 text-muted">{{ $i + 1 }}</td>
                                            <td class="px-2 py-1"><input wire:model.live="shots.{{ $i }}.position" type="number" min="1" class="w-14 rounded border border-border bg-surface px-2 py-1 text-sm"></td>
                                            <td class="px-2 py-1"><input wire:model.live="shots.{{ $i }}.gong_label" class="w-16 rounded border border-border bg-surface px-2 py-1 text-sm"></td>
                                            <td class="px-2 py-1"><input wire:model.live="shots.{{ $i }}.gong_name" class="w-20 rounded border border-border bg-surface px-2 py-1 text-sm"></td>
                                            <td class="px-2 py-1"><input wire:model.live="shots.{{ $i }}.distance_m" type="number" step="0.01" class="w-20 rounded border border-border bg-surface px-2 py-1 text-sm"></td>
                                            <td class="px-2 py-1"><input wire:model.live="shots.{{ $i }}.size_mm" type="number" class="w-18 rounded border border-border bg-surface px-2 py-1 text-sm"></td>
                                            <td class="px-2 py-1">
                                                <select wire:model.live="shots.{{ $i }}.shape" class="w-20 rounded border border-border bg-surface px-1 py-1 text-sm">
                                                    <option value="">—</option>
                                                    <option value="circle">Circle</option>
                                                    <option value="rectangle">Rect</option>
                                                    <option value="ipsc">IPSC</option>
                                                    <option value="plate">Plate</option>
                                                </select>
                                            </td>
                                            <td class="px-2 py-1 font-mono text-xs text-muted">{{ \App\Livewire\MatchbookStageEditor::shotMil($shot['size_mm'] ?? null, $shot['distance_m'] ?? null) ?? '—' }}</td>
                                            <td class="px-2 py-1 font-mono text-xs text-muted">{{ \App\Livewire\MatchbookStageEditor::shotMoa($shot['size_mm'] ?? null, $shot['distance_m'] ?? null) ?? '—' }}</td>
                                            <td class="px-2 py-1"><button wire:click="removeShot({{ $i }})" class="text-xs text-red-500 hover:underline">×</button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <flux:button variant="primary" wire:click="saveStage">Save stage</flux:button>
                        <flux:button variant="ghost" wire:click="closeStageModal">Cancel</flux:button>
                    </div>
                </div>

                <div class="rounded-xl border border-border bg-surface-2/40 p-4 lg:col-span-4">
                    <flux:heading size="md">Live Difficulty</flux:heading>
                    @php $diff = $this->difficultyPreview; @endphp
                    @if(!($diff['hasTargets'] ?? false))
                        <p class="mt-2 text-sm text-muted">Add shots to see difficulty.</p>
                    @else
                        <div class="mt-3 space-y-2">
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-bold tabular-nums">{{ $diff['score100'] }}</span>
                                <span class="text-sm text-muted">/ 100</span>
                            </div>
                            <flux:badge size="sm" color="{{ match ($diff['overallColor'] ?? '') { 'green' => 'green', 'amber' => 'amber', 'red' => 'red', default => 'zinc' } }}">{{ $diff['overallLabel'] ?? '—' }}</flux:badge>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </flux:modal>
</div>
