<?php

use App\Models\Setting;
use Flux\Flux;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Settings')]
    class extends Component {
    // Pricing
    public string $featured_match_price = '500';

    // Bank
    public string $bank_name = '';
    public string $bank_account_name = '';
    public string $bank_account_number = '';
    public string $bank_branch_code = '';
    public string $bank_reference_prefix = '';

    // Mailgun
    public string $mail_mailgun_domain = '';
    public string $mail_mailgun_secret = '';
    public string $mail_mailgun_endpoint = 'api.mailgun.net';
    public string $mail_from_address = '';
    public string $mail_from_name = '';
    public string $mail_test_recipient = '';

    public function mount(): void
    {
        $this->featured_match_price = (string) Setting::get('featured_match_price', '500');

        $this->bank_name = Setting::get('bank_name', '');
        $this->bank_account_name = Setting::get('bank_account_name', '');
        $this->bank_account_number = Setting::get('bank_account_number', '');
        $this->bank_branch_code = Setting::get('bank_branch_code', '');
        $this->bank_reference_prefix = Setting::get('bank_reference_prefix', 'DC');

        $this->mail_mailgun_domain = Setting::get('mail_mailgun_domain', '');
        $this->mail_mailgun_secret = Setting::get('mail_mailgun_secret', '');
        $this->mail_mailgun_endpoint = Setting::get('mail_mailgun_endpoint', 'api.mailgun.net');
        $this->mail_from_address = Setting::get('mail_from_address', '');
        $this->mail_from_name = Setting::get('mail_from_name', 'DeadCenter');

        $this->mail_test_recipient = auth()->user()->email;
    }

    public function savePricing(): void
    {
        $this->validate([
            'featured_match_price' => 'required|numeric|min:0',
        ]);

        Setting::set('featured_match_price', $this->featured_match_price);

        Flux::toast('Pricing saved.', variant: 'success');
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

    public function saveMailSettings(): void
    {
        $this->validate([
            'mail_mailgun_domain' => 'required|string|max:255',
            'mail_mailgun_secret' => 'required|string|max:255',
            'mail_mailgun_endpoint' => 'required|string|max:255',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        Setting::set('mail_mailgun_domain', $this->mail_mailgun_domain);
        Setting::set('mail_mailgun_secret', $this->mail_mailgun_secret);
        Setting::set('mail_mailgun_endpoint', $this->mail_mailgun_endpoint);
        Setting::set('mail_from_address', $this->mail_from_address);
        Setting::set('mail_from_name', $this->mail_from_name);

        config([
            'services.mailgun.domain' => $this->mail_mailgun_domain,
            'services.mailgun.secret' => $this->mail_mailgun_secret,
            'services.mailgun.endpoint' => $this->mail_mailgun_endpoint,
            'mail.from.address' => $this->mail_from_address,
            'mail.from.name' => $this->mail_from_name,
            'mail.default' => 'mailgun',
        ]);

        Flux::toast('Mail settings saved.', variant: 'success');
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'mail_test_recipient' => 'required|email',
            'mail_mailgun_domain' => 'required|string',
            'mail_mailgun_secret' => 'required|string',
            'mail_from_address' => 'required|email',
        ]);

        config([
            'services.mailgun.domain' => $this->mail_mailgun_domain,
            'services.mailgun.secret' => $this->mail_mailgun_secret,
            'services.mailgun.endpoint' => $this->mail_mailgun_endpoint,
            'mail.from.address' => $this->mail_from_address,
            'mail.from.name' => $this->mail_from_name,
            'mail.default' => 'mailgun',
        ]);

        try {
            Mail::raw('This is a test email from DeadCenter. If you received this, your Mailgun configuration is working correctly.', function ($message) {
                $message->to($this->mail_test_recipient)
                    ->subject('DeadCenter — Test Email');
            });

            Flux::toast('Test email sent to ' . $this->mail_test_recipient, variant: 'success');
        } catch (\Throwable $e) {
            Flux::toast('Failed: ' . $e->getMessage(), variant: 'danger');
        }
    }
}; ?>

<div class="space-y-6 max-w-2xl">
    <x-admin-tab-bar :tabs="[
        ['href' => route('admin.settings'), 'label' => 'General', 'active' => true],
        ['href' => route('admin.homepage'), 'label' => 'Homepage', 'active' => false],
        ['href' => route('admin.contact-submissions'), 'label' => 'Contact Inbox', 'active' => false],
    ]" />

    <div>
        <h1 class="text-2xl font-bold text-white">Platform Settings</h1>
        <p class="mt-1 text-sm text-secondary">Configure bank details, email delivery, and platform preferences.</p>
    </div>

    {{-- Mailgun / Email --}}
    <form wire:submit="saveMailSettings" class="space-y-6">
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-white">Email (Mailgun)</h2>
                @if($mail_mailgun_domain && $mail_mailgun_secret)
                    <span class="rounded-full bg-emerald-600/20 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-400">Configured</span>
                @else
                    <span class="rounded-full bg-amber-600/20 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-400">Not Set</span>
                @endif
            </div>
            <p class="text-sm text-muted">Configure Mailgun to send transactional emails (registration confirmations, password resets, etc.).</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:input wire:model="mail_mailgun_domain" label="Mailgun Domain" placeholder="e.g. mg.deadcenter.co.za" required />
                    <p class="mt-1 text-xs text-muted">Your verified sending domain in Mailgun.</p>
                </div>

                <div class="sm:col-span-2">
                    <flux:input wire:model="mail_mailgun_secret" label="Mailgun API Key" type="password" placeholder="key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required />
                    <p class="mt-1 text-xs text-muted">Found in Mailgun → Settings → API Keys → Private API key.</p>
                </div>

                <flux:input wire:model="mail_mailgun_endpoint" label="API Endpoint" placeholder="api.mailgun.net" required />
                <div>
                    <p class="text-xs text-muted mt-6">Use <code class="rounded bg-surface-2 px-1 py-0.5 text-secondary">api.eu.mailgun.net</code> for EU regions.</p>
                </div>
            </div>

            <flux:separator />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="mail_from_address" label="From Address" type="email" placeholder="noreply@deadcenter.co.za" required />
                <flux:input wire:model="mail_from_name" label="From Name" placeholder="DeadCenter" required />
            </div>

            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                    Save Mail Settings
                </flux:button>
            </div>
        </div>
    </form>

    {{-- Send Test Email --}}
    @if($mail_mailgun_domain && $mail_mailgun_secret)
    <div class="rounded-xl border border-dashed border-border bg-surface/50 p-6 space-y-4">
        <h3 class="text-sm font-semibold text-white">Send Test Email</h3>
        <p class="text-xs text-muted">Verify your Mailgun configuration by sending a test email.</p>
        <div class="flex items-end gap-3">
            <div class="flex-1">
                <flux:input wire:model="mail_test_recipient" label="Recipient" type="email" placeholder="you@example.com" />
            </div>
            <flux:button wire:click="sendTestEmail" variant="primary" class="!bg-emerald-600 hover:!bg-emerald-700">
                Send Test
            </flux:button>
        </div>
    </div>
    @endif

    {{-- Pricing --}}
    <form wire:submit="savePricing" class="space-y-6">
        <div class="rounded-xl border border-border bg-surface p-6 space-y-4">
            <h2 class="text-lg font-semibold text-white">Pricing</h2>
            <p class="text-sm text-muted">Set prices for platform features. These are shown to Match Directors when they request services.</p>

            <flux:input wire:model="featured_match_price" label="Featured Event Price (R)" type="number" min="0" step="1" placeholder="e.g. 500" required />
            <p class="text-xs text-muted">Match Directors pay this amount to have their event featured on the homepage and ranked higher in listings.</p>

            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" class="!bg-accent hover:!bg-accent-hover">
                    Save Pricing
                </flux:button>
            </div>
        </div>
    </form>

    {{-- Bank Details --}}
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
