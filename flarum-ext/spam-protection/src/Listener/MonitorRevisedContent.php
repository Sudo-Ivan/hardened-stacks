<?php

namespace HardenedStacks\SpamProtection\Listener;

use Flarum\Post\Event\Revised;
use HardenedStacks\SpamProtection\SpamMonitor;
use Throwable;

class MonitorRevisedContent
{
    public function __construct(
        private SpamMonitor $monitor
    ) {
    }

    public function handle(Revised $event): void
    {
        try {
            if (! $this->monitor->shouldMonitorEdits()) {
                return;
            }

            $this->monitor->reviewPost($event->post, 'edit');
        } catch (Throwable) {
        }
    }
}
