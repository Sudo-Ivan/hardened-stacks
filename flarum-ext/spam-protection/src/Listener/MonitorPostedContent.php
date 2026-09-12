<?php

namespace HardenedStacks\SpamProtection\Listener;

use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use HardenedStacks\SpamProtection\SpamMonitor;
use Throwable;

class MonitorPostedContent
{
    public function __construct(
        private SpamMonitor $monitor
    ) {
    }

    public function handle(Posted $event): void
    {
        try {
            if (! $this->monitor->shouldMonitorPosts()) {
                return;
            }

            $post = $event->post;
            if (! $post instanceof CommentPost) {
                return;
            }

            $this->monitor->reviewPost($post, 'post');
        } catch (Throwable) {
            // Never fail the create/edit request because of spam scanning.
        }
    }
}
