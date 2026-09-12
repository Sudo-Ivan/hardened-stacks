<?php

namespace HardenedStacks\Maintenance\Listener;

use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Foundation\ValidationException;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\User\Event\Saving as UserSaving;
use Flarum\User\User;
use HardenedStacks\Maintenance\MaintenanceState;

class BlockWriteOperations
{
    public function __construct(
        private MaintenanceState $state
    ) {
    }

    public function handle(PostSaving|DiscussionSaving|UserSaving $event): void
    {
        $actor = $event->actor ?? null;
        if (! $actor instanceof User) {
            return;
        }

        if (! $this->state->blocksWrites($actor)) {
            return;
        }

        throw new ValidationException([
            'maintenance' => [$this->state->message()],
        ]);
    }
}
