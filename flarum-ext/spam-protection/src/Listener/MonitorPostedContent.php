<?php

namespace HardenedStacks\SpamProtection\Listener;

use Flarum\Post\CommentPost;
use Flarum\Post\Event\Posted;
use HardenedStacks\SpamProtection\DeferredRunner;
use HardenedStacks\SpamProtection\SpamMonitor;

class MonitorPostedContent
{
    public function __construct(
        private SpamMonitor $monitor
    ) {
    }

    public function handle(Posted $event): void
    {
        if (! $this->monitor->shouldMonitorPosts()) {
            return;
        }

        $post = $event->post;
        if (! $post instanceof CommentPost) {
            return;
        }

        $author = $post->user;
        if (! $author || $author->isAdmin()) {
            return;
        }

        $discussion = $post->discussion;
        $content = (string) ($post->content ?? '');
        if ($content === '') {
            return;
        }

        $postId = (int) $post->id;
        $authorId = (int) $author->id;
        $context = [
            'content' => mb_substr($content, 0, 8000),
            'discussion_title' => (string) ($discussion->title ?? ''),
            'is_first_post' => (int) $post->number === 1,
            'author' => [
                'id' => $author->id,
                'username' => $author->username,
                'joined_at' => $author->joined_at?->toIso8601String(),
                'comment_count' => (int) $author->comment_count,
                'is_new_user' => $this->monitor->isNewUser($author),
            ],
        ];

        $monitor = $this->monitor;
        DeferredRunner::afterResponse(static function () use ($monitor, $postId, $authorId, $context): void {
            $post = CommentPost::query()->find($postId);
            $author = \Flarum\User\User::query()->find($authorId);
            if (! $post instanceof CommentPost || ! $author) {
                return;
            }

            $verdict = $monitor->classify('post', $context);
            $monitor->actions()->applyForPost($post, $author, $verdict);
        });
    }
}
