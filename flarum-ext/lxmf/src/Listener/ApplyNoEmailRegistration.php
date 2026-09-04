<?php

namespace HardenedStacks\Lxmf\Listener;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Event\Saving;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ApplyNoEmailRegistration
{
    public function __construct(
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(Saving $event): void
    {
        if ($event->user->exists) {
            return;
        }

        if (! (bool) $this->settings->get('hardened-stacks-lxmf.no_email_registration', '1')) {
            return;
        }

        $emailFromData = Arr::get($event->data, 'attributes.email');
        if (is_string($emailFromData) && trim($emailFromData) !== '') {
            return;
        }

        if (is_string($event->user->email) && trim($event->user->email) !== '') {
            return;
        }

        $domain = (string) $this->settings->get('hardened-stacks-lxmf.email_domain', 'noreply.invalid');
        $domain = strtolower(preg_replace('/[^a-z0-9.-]/i', '', $domain) ?: 'noreply.invalid');

        $username = (string) $event->user->username;
        $local = preg_replace('/[^a-z0-9_-]/i', '', $username) ?: 'user';
        $local = strtolower(substr($local, 0, 40));

        $event->user->email = sprintf('%s.%s@%s', $local, Str::lower(Str::random(12)), $domain);
        $event->user->activate();
    }
}
