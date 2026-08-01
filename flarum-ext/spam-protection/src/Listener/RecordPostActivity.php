<?php

namespace HardenedStacks\SpamProtection\Listener;

use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use HardenedStacks\SpamProtection\SpamChecker;

class RecordPostActivity
{
    public function __construct(
        private SpamChecker $checker
    ) {
    }

    public function handle(Posted $event): void
    {
        if (! $event->post instanceof CommentPost) {
            return;
        }

        $this->checker->markPosted($event->actor);
    }
}
