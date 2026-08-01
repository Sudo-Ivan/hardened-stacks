<?php

namespace HardenedStacks\UserManagement\Listener;

use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\User\Exception\PermissionDeniedException;
use HardenedStacks\UserManagement\Service\UserModerator;

class BlockLockedUserFromPosting
{
    public function __construct(
        private UserModerator $moderator
    ) {
    }

    public function handlePost(PostSaving $event): void
    {
        $this->assertCanPost($event->actor);
    }

    public function handleDiscussion(DiscussionSaving $event): void
    {
        if ($event->discussion->exists) {
            return;
        }

        $this->assertCanPost($event->actor);
    }

    private function assertCanPost($actor): void
    {
        if (! $actor || $actor->isGuest() || $actor->isAdmin()) {
            return;
        }

        if ($this->moderator->isSuspended($actor)) {
            throw new PermissionDeniedException('hardened-stacks-user-management.forum.suspended');
        }

        if ($this->moderator->isPostingLocked($actor)) {
            throw new PermissionDeniedException('hardened-stacks-user-management.forum.posting_locked');
        }
    }
}
