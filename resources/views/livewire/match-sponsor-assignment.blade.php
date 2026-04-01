<div class="space-y-4">
    @foreach($matchPlacements as $placementKey)
        @php $pVal = $placementKey->value; @endphp
        <div wire:key="placement-{{ $pVal }}">
            <flux:select
                wire:model="placementSelections.{{ $pVal }}"
                wire:change="savePlacement('{{ $pVal }}')"
                label="{{ $placementLabels[$pVal] ?? $placementKey->label() }}"
            >
                <option value="">Use platform default</option>
                @foreach($sponsors as $sponsor)
                    <option value="{{ $sponsor->id }}">{{ $sponsor->name }}</option>
                @endforeach
            </flux:select>
        </div>
    @endforeach
</div>
