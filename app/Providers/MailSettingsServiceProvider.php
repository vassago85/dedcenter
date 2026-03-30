<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class MailSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            $this->applyMailSettings();
        }

        $this->app->booted(function () {
            if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
                $this->applyMailSettings();
            }
        });
    }

    private function applyMailSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $keys = [
                'mail_mailgun_domain',
                'mail_mailgun_secret',
                'mail_mailgun_endpoint',
                'mail_from_address',
                'mail_from_name',
            ];

            $settings = Setting::whereIn('key', $keys)->pluck('value', 'key');

            if ($settings->isEmpty()) {
                return;
            }

            if ($domain = $settings->get('mail_mailgun_domain')) {
                config(['services.mailgun.domain' => $domain]);
            }

            if ($secret = $settings->get('mail_mailgun_secret')) {
                config(['services.mailgun.secret' => $secret]);
            }

            if ($endpoint = $settings->get('mail_mailgun_endpoint')) {
                config(['services.mailgun.endpoint' => $endpoint]);
            }

            if ($fromAddress = $settings->get('mail_from_address')) {
                config(['mail.from.address' => $fromAddress]);
            }

            if ($fromName = $settings->get('mail_from_name')) {
                config(['mail.from.name' => $fromName]);
            }

            if ($settings->get('mail_mailgun_domain') && $settings->get('mail_mailgun_secret')) {
                config(['mail.default' => 'mailgun']);
            }
        } catch (\Throwable) {
            // DB not available yet (migrations pending, etc.)
        }
    }
}
