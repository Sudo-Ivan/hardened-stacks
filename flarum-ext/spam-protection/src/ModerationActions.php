<?php

namespace HardenedStacks\SpamProtection;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ModerationActions
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private ConnectionInterface $db,
        private LoggerInterface $logger
    ) {
    }

    public function applyForPost(CommentPost $post, User $author, SpamVerdict $verdict): void
    {
        if (! $verdict->isSpam || $verdict->confidence < $this->minConfidence()) {
            $this->logger->info('hardened-stacks-spam-protection: post clean or below threshold', [
                'post_id' => $post->id,
                'user_id' => $author->id,
                'is_spam' => $verdict->isSpam,
                'confidence' => $verdict->confidence,
                'min_confidence' => $this->minConfidence(),
                'reason' => $verdict->reason,
            ]);

            return;
        }

        $actions = $this->resolveActions($verdict->actions);
        $reason = $this->reasonLabel($verdict);

        if (in_array('hide_post', $actions, true) && $this->settingEnabled('action_hide_post')) {
            $this->hidePost($post);
        }

        $discussion = $post->discussion;
        if ($discussion instanceof Discussion) {
            if (in_array('hide_discussion', $actions, true) && $this->settingEnabled('action_hide_discussion')) {
                $this->hideDiscussion($discussion);
            }

            if (in_array('lock_discussion', $actions, true) && $this->settingEnabled('action_lock_discussion')) {
                $this->lockDiscussion($discussion);
            }
        }

        // Never suspend administrators even when their content is moderated.
        if (
            ! $author->isAdmin()
            && in_array('suspend_user', $actions, true)
            && $this->settingEnabled('action_suspend_user')
        ) {
            $this->suspendUser($author, $reason);
        }

        $this->logger->info('hardened-stacks-spam-protection: moderated post', [
            'post_id' => $post->id,
            'user_id' => $author->id,
            'confidence' => $verdict->confidence,
            'actions' => $actions,
            'reason' => $verdict->reason,
        ]);
    }

    public function applyForDiscussion(Discussion $discussion, User $author, SpamVerdict $verdict): void
    {
        if (! $verdict->isSpam || $verdict->confidence < $this->minConfidence()) {
            return;
        }

        $actions = $this->resolveActions($verdict->actions);
        $reason = $this->reasonLabel($verdict);

        if (in_array('hide_discussion', $actions, true) && $this->settingEnabled('action_hide_discussion')) {
            $this->hideDiscussion($discussion);
        }

        if (in_array('lock_discussion', $actions, true) && $this->settingEnabled('action_lock_discussion')) {
            $this->lockDiscussion($discussion);
        }

        if (in_array('hide_post', $actions, true) && $this->settingEnabled('action_hide_post')) {
            $firstPost = $discussion->firstPost;
            if ($firstPost instanceof CommentPost) {
                $this->hidePost($firstPost);
            }
        }

        if (
            ! $author->isAdmin()
            && in_array('suspend_user', $actions, true)
            && $this->settingEnabled('action_suspend_user')
        ) {
            $this->suspendUser($author, $reason);
        }

        $this->logger->info('hardened-stacks-spam-protection: moderated discussion', [
            'discussion_id' => $discussion->id,
            'user_id' => $author->id,
            'confidence' => $verdict->confidence,
            'actions' => $actions,
            'reason' => $verdict->reason,
        ]);
    }

    public function applyForUser(User $user, SpamVerdict $verdict): void
    {
        if (! $verdict->isSpam || $verdict->confidence < $this->minConfidence()) {
            return;
        }

        $actions = $this->resolveActions($verdict->actions);
        $reason = $this->reasonLabel($verdict);

        if (
            ! $user->isAdmin()
            && in_array('suspend_user', $actions, true)
            && $this->settingEnabled('action_suspend_user')
        ) {
            $this->suspendUser($user, $reason);
        }

        $this->logger->info('hardened-stacks-spam-protection: moderated user', [
            'user_id' => $user->id,
            'confidence' => $verdict->confidence,
            'actions' => $actions,
            'reason' => $verdict->reason,
        ]);
    }

    /**
     * @param list<string> $requested
     * @return list<string>
     */
    private function resolveActions(array $requested): array
    {
        $allowed = [
            'hide_post',
            'hide_discussion',
            'lock_discussion',
            'suspend_user',
        ];

        $resolved = array_values(array_intersect($allowed, $requested));
        if ($resolved !== []) {
            return $resolved;
        }

        return ['hide_post'];
    }

    private function hidePost(Post $post): void
    {
        try {
            if (method_exists($post, 'hide')) {
                $post->hide();
                $post->save();
            }
        } catch (Throwable $e) {
            $this->logger->warning('hardened-stacks-spam-protection: hide post failed', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function hideDiscussion(Discussion $discussion): void
    {
        try {
            if (method_exists($discussion, 'hide')) {
                $discussion->hide();
                $discussion->save();
            }
        } catch (Throwable $e) {
            $this->logger->warning('hardened-stacks-spam-protection: hide discussion failed', [
                'discussion_id' => $discussion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function lockDiscussion(Discussion $discussion): void
    {
        if (! $this->db->getSchemaBuilder()->hasColumn('discussions', 'is_locked')) {
            return;
        }

        try {
            if ((bool) $discussion->is_locked) {
                return;
            }

            $discussion->is_locked = true;
            $discussion->save();
        } catch (Throwable $e) {
            $this->logger->warning('hardened-stacks-spam-protection: lock discussion failed', [
                'discussion_id' => $discussion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function suspendUser(User $user, string $reason): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $schema = $this->db->getSchemaBuilder();
        if (! $schema->hasColumn('users', 'suspended_until')) {
            return;
        }

        try {
            $days = max(1, (int) $this->settings->get('hardened-stacks-spam-protection.suspend_days', 30));
            $user->suspended_until = Carbon::now()->addDays($days);

            if ($schema->hasColumn('users', 'suspend_reason')) {
                $user->suspend_reason = $reason;
            }

            if ($schema->hasColumn('users', 'suspend_message')) {
                $user->suspend_message = $reason;
            }

            $user->save();
        } catch (Throwable $e) {
            $this->logger->warning('hardened-stacks-spam-protection: suspend user failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function reasonLabel(SpamVerdict $verdict): string
    {
        $reason = trim($verdict->reason);

        return $reason !== ''
            ? 'AI spam protection: '.$reason
            : 'AI spam protection';
    }

    private function minConfidence(): int
    {
        return max(0, min(100, (int) $this->settings->get('hardened-stacks-spam-protection.min_confidence', 70)));
    }

    private function settingEnabled(string $key): bool
    {
        return (bool) (int) $this->settings->get('hardened-stacks-spam-protection.'.$key, 1);
    }
}
