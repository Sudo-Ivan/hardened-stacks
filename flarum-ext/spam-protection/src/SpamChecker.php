<?php

namespace HardenedStacks\SpamProtection;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Contracts\Cache\Repository as Cache;

class SpamChecker
{
    public function __construct(
        private SettingsRepositoryInterface $settings,
        private Cache $cache
    ) {
    }

    public function assertContentAllowed(User $actor, ?string $content, string $reasonKey, bool $checkRateLimit = true): void
    {
        if ($actor->isAdmin() || $content === null || $content === '') {
            return;
        }

        if ($checkRateLimit) {
            $this->assertRateLimit($actor, $reasonKey);
        }

        $this->assertLinkLimits($actor, $content, $reasonKey);
        $this->assertUrlRatio($content, $reasonKey);
    }

    public function markPosted(User $actor): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        $interval = (int) $this->settings->get('hardened-stacks-spam-protection.min_post_interval', 30);
        if ($interval <= 0) {
            return;
        }

        $cacheKey = 'pmg_spam_last_post_'.$actor->id;
        $this->cache->put($cacheKey, time(), max($interval * 2, 300));
    }

    private function assertRateLimit(User $actor, string $reasonKey): void
    {
        $interval = (int) $this->settings->get('hardened-stacks-spam-protection.min_post_interval', 30);
        if ($interval <= 0) {
            return;
        }

        $cacheKey = 'pmg_spam_last_post_'.$actor->id;
        $lastPostAt = $this->cache->get($cacheKey);

        if (is_int($lastPostAt) && (time() - $lastPostAt) < $interval) {
            throw new PermissionDeniedException($reasonKey.'.rate_limited');
        }
    }

    private function assertLinkLimits(User $actor, string $content, string $reasonKey): void
    {
        $linkCount = $this->countLinks($content);
        $maxLinks = (int) $this->settings->get('hardened-stacks-spam-protection.max_links', 3);

        if ($maxLinks > 0 && $linkCount > $maxLinks) {
            throw new PermissionDeniedException($reasonKey.'.too_many_links');
        }

        if (! $this->isNewUser($actor)) {
            return;
        }

        $newUserMaxLinks = (int) $this->settings->get('hardened-stacks-spam-protection.new_user_max_links', 1);
        if ($newUserMaxLinks >= 0 && $linkCount > $newUserMaxLinks) {
            throw new PermissionDeniedException($reasonKey.'.too_many_links_new_user');
        }
    }

    private function assertUrlRatio(string $content, string $reasonKey): void
    {
        $maxRatio = (int) $this->settings->get('hardened-stacks-spam-protection.max_url_ratio', 50);
        if ($maxRatio <= 0 || $maxRatio >= 100) {
            return;
        }

        $plain = trim(strip_tags($content));
        $length = strlen($plain);
        if ($length < 40) {
            return;
        }

        $urlLength = 0;
        if (preg_match_all('/https?:\/\/[^\s<>"\']+/i', $plain, $matches)) {
            foreach ($matches[0] as $url) {
                $urlLength += strlen($url);
            }
        }

        if (($urlLength / $length) * 100 > $maxRatio) {
            throw new PermissionDeniedException($reasonKey.'.too_link_heavy');
        }
    }

    private function isNewUser(User $actor): bool
    {
        $newUserDays = (int) $this->settings->get('hardened-stacks-spam-protection.new_user_days', 7);
        $newUserPostCount = (int) $this->settings->get('hardened-stacks-spam-protection.new_user_post_count', 5);

        if ($newUserDays > 0 && $actor->joined_at && $actor->joined_at->diffInDays(now()) < $newUserDays) {
            return true;
        }

        if ($newUserPostCount > 0 && (int) $actor->posts_count < $newUserPostCount) {
            return true;
        }

        return false;
    }

    private function countLinks(string $content): int
    {
        $count = 0;

        if (preg_match_all('/https?:\/\/[^\s<>"\']+/i', $content, $rawLinks)) {
            $count += count($rawLinks[0]);
        }

        if (preg_match_all('/\[[^\]]*\]\((https?:\/\/[^)]+)\)/i', $content, $markdownLinks)) {
            $count += count($markdownLinks[1]);
        }

        if (preg_match_all('/\bwww\.[^\s<>"\']+/i', $content, $wwwLinks)) {
            $count += count($wwwLinks[0]);
        }

        return $count;
    }
}
