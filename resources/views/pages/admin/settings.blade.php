<?php

use App\Models\Setting;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Settings')]
    class extends Component {
    public string $bank_name = '';
    public string $bank_account_name = '';
    public string $bank_account_number = '';
    public string $bank_branch_code = '';
    public string $bank_reference_prefix = '';

    public function mount(): void
    {
        $this->bank_name = Setting::get('bank_name', '');
        $this->bank_account_name = Setting::get('bank_account_name', '');
        $this->bank_account_number = Setting::get('bank_account_number', '');
        $this->bank_branch_code = Setting::get('bank_branch_code', '');
        $this->bank_reference_prefix = Setting::get('bank_reference_prefix', 'DC');
    }

    public function save(): void
    {
        $this->validate([
            'bank_name' => 'required|string|max:255',
            'bank_account_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:50',
            'bank_branch_code' => 'required|string|max:20',
            'bank_reference_prefix' => 'required|string|max:10',
        ]);

        Setting::set('bank_name', $this->bank_name);
        Setting::set('bank_account_name', $this->bank_account_name);
        Setting::set('bank_account_number', $this->bank_account_number);
        Setting::set('bank_branch_code', $this->bank_branch_code);
        Setting::set('bank_reference_prefix', $this->bank_reference_prefix);

        Flux::toast('Settings saved.', variant: 'success');
    }
}; ?>

<div class="space-y-6 max-w-2xl">
    <div>
        <h1 class="text-2xl font-bold text-white">Platform Settings</h1>
        <p class="mt-1 text-sm text-secondary">Configure bank details for EFT payments.</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">Bank Details</h2>
            <p class="text-sm text-muted">These details are shown to members when they register for a match.</p>

            <flux:input wire:model="bank_name" label="Bank Name" placeholder="e.g. FNB" required />
            <flux:input wire:model="bank_account_name" label="Account Name" placeholder="e.g. DeadCenter Shooting" required />
            <flux:input wire:model="bank_account_number" label="Account Number" placeholder="e.g. 62000000000" required />
            <flux:input wire:model="bank_branch_code" label="Branch Code" placeholder="e.g. 250655" required />

            <flux:separator />

            <flux:input wire:model="bank_reference_prefix" label="Payment Reference Prefix" placeholder="e.g. DC" required />
            <p class="text-xs text-muted">References will be generated as PREFIX-SURNAME-0000</p>

            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                    Save Settings
                </flux:button>
            </div>
        </div>
    </form>
</div>
