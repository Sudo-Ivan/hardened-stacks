<?php

namespace HardenedStacks\SpamProtection;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

class SpamMonitor
{
    public function __construct(
        private AiClient $ai,
        private ModerationActions $actions,
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function shouldMonitorPosts(): bool
    {
        return $this->ai->isEnabled()
            && (bool) (int) $this->settings->get('hardened-stacks-spam-protection.monitor_posts', 1);
    }

    public function shouldMonitorNewUsers(): bool
    {
        return $this->ai->isEnabled()
            && (bool) (int) $this->settings->get('hardened-stacks-spam-protection.monitor_new_users', 1);
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

    /**
     * @param array<string, mixed> $context
     */
    public function classify(string $kind, array $context): SpamVerdict
    {
        return $this->ai->classify($kind, $context);
    }

    public function actions(): ModerationActions
    {
        return $this->actions;
    }
}
