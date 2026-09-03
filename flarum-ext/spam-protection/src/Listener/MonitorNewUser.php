<?php

namespace HardenedStacks\SpamProtection\Listener;

use Flarum\User\Event\Registered;
use Flarum\User\User;
use HardenedStacks\SpamProtection\DeferredRunner;
use HardenedStacks\SpamProtection\SpamMonitor;

class MonitorNewUser
{
    public function __construct(
        private SpamMonitor $monitor
    ) {
    }

    public function handle(Registered $event): void
    {
        if (! $this->monitor->shouldMonitorNewUsers()) {
            return;
        }

        $user = $event->user;
        if ($user->isAdmin()) {
            return;
        }

        $userId = (int) $user->id;
        $context = [
            'username' => (string) $user->username,
            'email_domain' => $this->emailDomain((string) $user->email),
            'joined_at' => $user->joined_at?->toIso8601String(),
        ];

        $monitor = $this->monitor;
        DeferredRunner::afterResponse(static function () use ($monitor, $userId, $context): void {
            $user = User::query()->find($userId);
            if (! $user) {
                return;
            }

            $verdict = $monitor->classify('new_user', $context);
            $monitor->actions()->applyForUser($user, $verdict);
        });
    }

    private function emailDomain(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) < 2) {
            return '';
        }

        return strtolower((string) end($parts));
    }
}
