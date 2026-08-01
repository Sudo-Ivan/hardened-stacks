<?php

namespace HardenedStacks\SpamProtection\Listener;

use Flarum\Discussion\Event\Saving;
use Illuminate\Support\Arr;
use HardenedStacks\SpamProtection\SpamChecker;

class ValidateDiscussionContent
{
    public function __construct(
        private SpamChecker $checker
    ) {
    }

    public function handle(Saving $event): void
    {
        if ($event->discussion->exists) {
            return;
        }

        $title = Arr::get($event->data, 'attributes.title');
        if ($title === null) {
            return;
        }

        $this->checker->assertContentAllowed(
            $event->actor,
            (string) $title,
            'hardened-stacks-spam-protection.forum.discussion',
            false,
            true
        );
    }
}
