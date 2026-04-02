<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Terms and Conditions — DeadCenter')]
    class extends Component {
}; ?>

<section class="py-20 lg:py-28" style="border-bottom: 1px solid var(--lp-border); background: var(--lp-bg-2);">
    <div class="mx-auto max-w-3xl px-6">
        <h1 class="mb-4 text-3xl font-bold tracking-tight lg:text-4xl" style="color: var(--lp-text);">Terms and Conditions</h1>
        <p class="mb-12 text-sm" style="color: var(--lp-text-muted);">Last updated: {{ now()->format('d F Y') }}</p>

        <div class="space-y-10 text-sm leading-relaxed" style="color: var(--lp-text-soft);">

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">1. Acceptance of Terms</h2>
                <p>By creating an account and using the DeadCenter platform, you agree to be bound by these terms and conditions and our <a href="{{ route('privacy') }}" style="color: var(--lp-red);">Privacy Policy</a>. If you do not agree, do not use the platform.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">2. Description of Service</h2>
                <p>DeadCenter is a competitive shooting scoring platform that allows match directors to set up and score matches, and shooters to register for matches, view scores, and track their results. The platform is provided as-is for the South African competitive shooting community.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">3. Account Responsibilities</h2>
                <ul class="list-disc space-y-1 pl-6">
                    <li>You must provide accurate information when creating your account.</li>
                    <li>You are responsible for maintaining the security of your account credentials.</li>
                    <li>You must not share your account with others or create multiple accounts.</li>
                    <li>You must be at least 18 years old to create an account, or have parental/guardian consent.</li>
                </ul>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">4. Public Display of Name</h2>
                <p>You agree that your name will be displayed publicly on score sheets, match standings, leaderboards, and season results. This is a core function of the platform and cannot be opted out of while using the service.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">5. Match Registration</h2>
                <p>When registering for a match, you agree to share relevant information (as requested by the match director) with the organizing body. Payment terms and refund policies for individual matches are determined by the respective match directors and organizations, not by DeadCenter.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">6. Acceptable Use</h2>
                <p>You agree not to:</p>
                <ul class="mt-2 list-disc space-y-1 pl-6">
                    <li>Use the platform for any unlawful purpose.</li>
                    <li>Attempt to access other users&rsquo; accounts or data.</li>
                    <li>Interfere with match scoring or manipulate results.</li>
                    <li>Misrepresent your identity or impersonate others.</li>
                    <li>Use automated systems to access the platform without permission.</li>
                </ul>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">7. Score Accuracy &amp; Disputes</h2>
                <p>Match scores are recorded by designated scorers and match directors. While we strive for accuracy, DeadCenter is not responsible for scoring errors. Score disputes should be raised with the relevant match director or organization directly.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">8. Limitation of Liability</h2>
                <p>DeadCenter is provided &ldquo;as is&rdquo; without warranties of any kind. We are not liable for any loss or damage arising from your use of the platform, including but not limited to data loss, service interruptions, or scoring inaccuracies.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">9. Termination</h2>
                <p>We reserve the right to suspend or terminate accounts that violate these terms. You may delete your account at any time by contacting us.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">10. Changes to Terms</h2>
                <p>We may update these terms from time to time. Continued use of the platform after changes constitutes acceptance. Significant changes will be communicated via email or in-app notice.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">11. Governing Law</h2>
                <p>These terms are governed by the laws of the Republic of South Africa.</p>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-semibold" style="color: var(--lp-text);">12. Contact</h2>
                <p>For questions about these terms, contact us at <a href="mailto:info@deadcenter.co.za" style="color: var(--lp-red);">info@deadcenter.co.za</a>.</p>
            </div>

        </div>
    </div>
</section>
