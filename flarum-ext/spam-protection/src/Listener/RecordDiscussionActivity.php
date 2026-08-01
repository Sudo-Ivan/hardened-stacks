<?php

namespace HardenedStacks\SpamProtection\Listener;

use Flarum\Discussion\Event\Started;
use HardenedStacks\SpamProtection\SpamChecker;

class RecordDiscussionActivity
{
    public function __construct(
        private SpamChecker $checker
    ) {
    }

    public function handle(Started $event): void
    {
        $title = $event->discussion->title ?? '';
        if ($title === '') {
            return;
        }

        $this->checker->rememberContent($event->actor, $title);
    }
}
