<?php

namespace HardenedStacks\Maintenance\Listener;

use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Foundation\ValidationException;
use Flarum\Post\Event\Saving as PostSaving;
use HardenedStacks\Maintenance\MaintenanceState;

class BlockWriteOperations
{
    public function __construct(
        private MaintenanceState $state
    ) {
    }

    public function handle(PostSaving|DiscussionSaving $event): void
    {
        if (! $this->state->blocksWrites($event->actor)) {
            return;
        }

        throw new ValidationException([
            'maintenance' => [$this->state->message()],
        ]);
    }
}
