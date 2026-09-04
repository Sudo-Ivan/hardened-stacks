<?php

namespace HardenedStacks\SpamProtection;

use Flarum\Post\CommentPost;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Psr\Log\LoggerInterface;
use Throwable;

class SpamMonitor
{
    public function __construct(
        private AiClient $ai,
        private ModerationActions $actions,
        private SettingsRepositoryInterface $settings,
        private AuditLogger $audit,
        private LoggerInterface $logger
    ) {
    }

    public function shouldMonitorPosts(): bool
    {
        return $this->ai->isEnabled()
            && (bool) (int) $this->settings->get('hardened-stacks-spam-protection.monitor_posts', 1);
    }

    public function shouldMonitorEdits(): bool
    {
        return $this->shouldMonitorPosts()
            && (bool) (int) $this->settings->get('hardened-stacks-spam-protection.monitor_edits', 1);
    }

    public function shouldMonitorNewUsers(): bool
    {
        return $this->ai->isEnabled()
            && (bool) (int) $this->settings->get('hardened-stacks-spam-protection.monitor_new_users', 1);
    }

    public function isConfigured(): bool
    {
        return $this->ai->isConfigured();
    }

    public function isEnabled(): bool
    {
        return $this->ai->isEnabled();
    }

    public function isNewUser(User $user): bool
    {
        $newUserDays = (int) $this->settings->get('hardened-stacks-spam-protection.new_user_days', 14);
        $newUserPostCount = (int) $this->settings->get('hardened-stacks-spam-protection.new_user_post_count', 10);

        if ($newUserDays > 0 && $user->joined_at && $user->joined_at->diffInDays(now()) < $newUserDays) {
            return true;
        }

        if ($newUserPostCount > 0 && (int) $user->comment_count < $newUserPostCount) {
            return true;
        }

        return false;
    }

    public function reviewPost(CommentPost $post, string $kind = 'post'): SpamVerdict
    {
        $author = $post->user;
        if (! $author) {
            $verdict = SpamVerdict::clean();
            $this->audit->record($kind, (int) $post->id, (int) ($post->discussion_id ?? 0) ?: null, null, $verdict, 'skipped', 'Post has no author');

            return $verdict;
        }

        $discussion = $post->discussion;
        $content = (string) ($post->content ?? '');
        if ($content === '') {
            $verdict = SpamVerdict::clean();
            $this->audit->record($kind, (int) $post->id, (int) ($post->discussion_id ?? 0) ?: null, (int) $author->id, $verdict, 'skipped', 'Empty content');

            return $verdict;
        }

        $context = [
            'content' => mb_substr($content, 0, 8000),
            'discussion_title' => (string) ($discussion->title ?? ''),
            'is_first_post' => (int) $post->number === 1,
            'author' => [
                'id' => $author->id,
                'username' => $author->username,
                'joined_at' => $author->joined_at?->toIso8601String(),
                'comment_count' => (int) $author->comment_count,
                'is_admin' => $author->isAdmin(),
                'is_new_user' => $this->isNewUser($author),
            ],
        ];

        try {
            $verdict = $this->ai->classify($kind, $context);
            $this->actions->applyForPost($post, $author, $verdict);
            $this->audit->record(
                $kind,
                (int) $post->id,
                (int) ($post->discussion_id ?? 0) ?: null,
                (int) $author->id,
                $verdict,
                $verdict->isSpam ? 'actioned' : 'clean'
            );

            return $verdict;
        } catch (Throwable $e) {
            $this->logger->error('hardened-stacks-spam-protection: reviewPost failed', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            $verdict = SpamVerdict::clean();
            $this->audit->record(
                $kind,
                (int) $post->id,
                (int) ($post->discussion_id ?? 0) ?: null,
                (int) $author->id,
                $verdict,
                'error',
                $e->getMessage()
            );

            return $verdict;
        }
    }

    public function reviewUser(User $user, string $kind = 'new_user'): SpamVerdict
    {
        $context = [
            'username' => (string) $user->username,
            'email_domain' => $this->emailDomain((string) $user->email),
            'joined_at' => $user->joined_at?->toIso8601String(),
        ];

        try {
            $verdict = $this->ai->classify($kind, $context);
            $this->actions->applyForUser($user, $verdict);
            $this->audit->record($kind, null, null, (int) $user->id, $verdict, $verdict->isSpam ? 'actioned' : 'clean');

            return $verdict;
        } catch (Throwable $e) {
            $this->logger->error('hardened-stacks-spam-protection: reviewUser failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $verdict = SpamVerdict::clean();
            $this->audit->record($kind, null, null, (int) $user->id, $verdict, 'error', $e->getMessage());

            return $verdict;
        }
    }

    public function actions(): ModerationActions
    {
        return $this->actions;
    }

    public function audit(): AuditLogger
    {
        return $this->audit;
    }

    private function emailDomain(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) < 2) {
            return '';
        }

        return strtolower((string) end($parts));
    }
}
