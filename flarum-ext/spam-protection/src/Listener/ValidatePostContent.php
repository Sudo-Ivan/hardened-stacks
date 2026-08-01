<?php

namespace HardenedStacks\SpamProtection\Listener;

use Flarum\Post\CommentPost;
use Flarum\Post\Event\Saving;
use Illuminate\Support\Arr;
use HardenedStacks\SpamProtection\SpamChecker;

class ValidatePostContent
{
    public function __construct(
        private SpamChecker $checker
    ) {
    }

    public function handle(Saving $event): void
    {
        if (! $event->post instanceof CommentPost) {
            return;
        }

        $content = Arr::get($event->data, 'attributes.content');
        if ($content === null) {
            return;
        }

        $this->checker->assertContentAllowed(
            $event->actor,
            (string) $content,
            'hardened-stacks-spam-protection.forum.post',
            ! $event->post->exists
        );
    }
}
