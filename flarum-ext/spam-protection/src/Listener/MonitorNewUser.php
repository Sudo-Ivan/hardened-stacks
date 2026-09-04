<?php

namespace HardenedStacks\SpamProtection\Listener;

use Flarum\User\Event\Registered;
use HardenedStacks\SpamProtection\SpamMonitor;
use Throwable;

class MonitorNewUser
{
    public function __construct(
        private SpamMonitor $monitor
    ) {
    }

    public function handle(Registered $event): void
    {
        try {
            if (! $this->monitor->shouldMonitorNewUsers()) {
                return;
            }

            $user = $event->user;
            if ($user->isAdmin()) {
                return;
            }

            $this->monitor->reviewUser($user, 'new_user');
        } catch (Throwable) {
        }
    }
}
