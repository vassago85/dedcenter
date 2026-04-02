<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Privacy Policy — DeadCenter')]
    class extends Component {
}; ?>

<section class="py-20 lg:py-28" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-3xl px-6">
        <h1 class="mb-4 text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Privacy Policy</h1>
        <p class="mb-12 text-sm" style="color: var(--lp-text-muted);">Last updated: {{ now()->format('d F Y') }}</p>

        <div class="space-y-10 text-sm leading-relaxed" style="color: var(--lp-text-soft);">

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">1. What We Collect</h2>
                <p>When you create an account on DeadCenter, we collect:</p>
                <ul class="mt-2 list-disc space-y-1 pl-6">
                    <li><strong style="color: var(--lp-text);">Name</strong> &mdash; used to identify you on score sheets, standings, and match registrations.</li>
                    <li><strong style="color: var(--lp-text);">Email address</strong> &mdash; used for account login, email verification, and match-related notifications.</li>
                    <li><strong style="color: var(--lp-text);">Password</strong> &mdash; stored securely using one-way hashing. We never have access to your plain-text password.</li>
                </ul>
                <p class="mt-3">When you register for a match, you may optionally provide additional information (e.g. caliber, equipment details, contact number) as required by the match director. This information is only shared with the organizing body of that specific match.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">2. How Your Name Appears Publicly</h2>
                <p>By using DeadCenter, you acknowledge that your <strong style="color: var(--lp-text);">name will appear on score sheets, match standings, leaderboards, and season results</strong>. This is essential to the function of a competitive shooting scoring platform.</p>
                <p class="mt-2">No other personal information (email, contact number, equipment details, or registration data) is displayed publicly or shared outside of the organization you registered with for a specific match.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">3. Who Sees Your Information</h2>
                <ul class="list-disc space-y-1 pl-6">
                    <li><strong style="color: var(--lp-text);">General public:</strong> Only your name on score sheets and standings.</li>
                    <li><strong style="color: var(--lp-text);">Organization admins / Match directors:</strong> Your name, email, and any registration details you provided when signing up for their match. This is necessary for match coordination, squadding, and communication.</li>
                    <li><strong style="color: var(--lp-text);">Site administrators:</strong> Full access for platform support and maintenance.</li>
                </ul>
                <p class="mt-3">Your information is <strong style="color: var(--lp-text);">never sold, rented, or shared</strong> with third parties for marketing purposes.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">4. Notifications</h2>
                <p>We may send you email notifications about matches you have registered for, including registration confirmations, squadding updates, score publications, and match reminders. You can manage your notification preferences in your account settings.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">5. Data Storage &amp; Security</h2>
                <p>Your data is stored on secured servers. Passwords are hashed and never stored in plain text. API access is protected by token-based authentication. We take reasonable precautions to protect your personal information, but no system is completely secure.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">6. Data Retention</h2>
                <p>Your account and associated data are retained for as long as your account is active. Match results and standings are retained indefinitely as part of the historical competition record. If you wish to delete your account, contact us and we will remove your personal data while preserving anonymized competition records.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">7. Cookies &amp; Analytics</h2>
                <p>We use essential cookies required for authentication and session management. We may use analytics tools to understand how the platform is used. No advertising cookies or trackers are used.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">8. Changes to This Policy</h2>
                <p>We may update this privacy policy from time to time. Significant changes will be communicated via email or an in-app notice. Continued use of the platform after changes constitutes acceptance of the updated policy.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">9. Contact</h2>
                <p>If you have questions about this privacy policy or your personal data, contact us at <a href="mailto:info@deadcenter.co.za" style="color: var(--lp-red);">info@deadcenter.co.za</a>.</p>
            </div>

        </div>
    </div>
</section>
