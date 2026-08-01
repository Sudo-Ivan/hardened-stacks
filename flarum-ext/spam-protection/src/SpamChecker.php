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

    public function assertContentAllowed(User $actor, ?string $content, string $reasonKey, bool $checkActivityLimits = true, bool $checkDuplicate = true): void
    {
        if ($actor->isAdmin() || $content === null || $content === '') {
            return;
        }

        $this->assertNewUserPostDelay($actor, $reasonKey);

        if ($checkActivityLimits) {
            $this->assertRateLimit($actor, $reasonKey);
            $this->assertBurstLimit($actor, $reasonKey);
        }

        if ($checkDuplicate) {
            $this->assertDuplicateContent($actor, $content, $reasonKey);
        }

        $this->assertOptionalLinkCaps($actor, $content, $reasonKey);
        $this->assertLinkContext($actor, $content, $reasonKey);
        $this->assertOptionalUrlRatio($actor, $content, $reasonKey);
    }

    public function markPosted(User $actor, ?string $content = null): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        $this->touchRateLimit($actor);
        $this->incrementBurstCounter($actor);

        if ($content !== null && $content !== '') {
            $this->rememberContent($actor, $content);
        }
    }

    private function assertNewUserPostDelay(User $actor, string $reasonKey): void
    {
        if (! $this->isNewUserPostDelayEnabled()) {
            return;
        }

        $delay = (int) $this->settings->get('hardened-stacks-spam-protection.new_user_post_delay', 3600);
        if ($delay <= 0 || ! $actor->joined_at) {
            return;
        }

        $secondsSinceJoin = $actor->joined_at->diffInSeconds(now());
        if ($secondsSinceJoin < $delay) {
            throw new PermissionDeniedException($reasonKey.'.new_user_post_delay');
        }
    }

    private function isNewUserPostDelayEnabled(): bool
    {
        return (bool) (int) $this->settings->get('hardened-stacks-spam-protection.new_user_post_delay_enabled', 1);
    }

    private function assertRateLimit(User $actor, string $reasonKey): void
    {
        $interval = $this->postIntervalFor($actor);
        if ($interval <= 0) {
            return;
        }

        $lastPostAt = $this->cache->get($this->rateLimitKey($actor));

        if (is_int($lastPostAt) && (time() - $lastPostAt) < $interval) {
            throw new PermissionDeniedException($reasonKey.'.rate_limited');
        }
    }

    private function touchRateLimit(User $actor): void
    {
        $interval = $this->postIntervalFor($actor);
        if ($interval <= 0) {
            return;
        }

        $this->cache->put($this->rateLimitKey($actor), time(), max($interval * 4, 300));
    }

    private function postIntervalFor(User $actor): int
    {
        if ($this->isNewUser($actor)) {
            return (int) $this->settings->get('hardened-stacks-spam-protection.new_user_min_post_interval', 20);
        }

        return (int) $this->settings->get('hardened-stacks-spam-protection.min_post_interval', 8);
    }

    private function assertBurstLimit(User $actor, string $reasonKey): void
    {
        if (! $this->isNewUser($actor)) {
            return;
        }

        $maxPerHour = (int) $this->settings->get('hardened-stacks-spam-protection.burst_posts_hour', 20);
        if ($maxPerHour <= 0) {
            return;
        }

        $count = (int) $this->cache->get($this->burstKey($actor), 0);
        if ($count >= $maxPerHour) {
            throw new PermissionDeniedException($reasonKey.'.burst_limited');
        }
    }

    private function incrementBurstCounter(User $actor): void
    {
        if (! $this->isNewUser($actor)) {
            return;
        }

        $maxPerHour = (int) $this->settings->get('hardened-stacks-spam-protection.burst_posts_hour', 20);
        if ($maxPerHour <= 0) {
            return;
        }

        $key = $this->burstKey($actor);
        $count = (int) $this->cache->get($key, 0);
        $this->cache->put($key, $count + 1, 3600);
    }

    private function assertDuplicateContent(User $actor, string $content, string $reasonKey): void
    {
        $window = (int) $this->settings->get('hardened-stacks-spam-protection.duplicate_window', 900);
        if ($window <= 0) {
            return;
        }

        $hash = hash('sha256', $this->normalizeContent($content));
        $key = $this->duplicateKey($actor, $hash);

        if ($this->cache->has($key)) {
            throw new PermissionDeniedException($reasonKey.'.duplicate_content');
        }
    }

    public function rememberContent(User $actor, string $content): void
    {
        $window = (int) $this->settings->get('hardened-stacks-spam-protection.duplicate_window', 900);
        if ($window <= 0) {
            return;
        }

        $hash = hash('sha256', $this->normalizeContent($content));
        $this->cache->put($this->duplicateKey($actor, $hash), 1, $window);
    }

    private function assertOptionalLinkCaps(User $actor, string $content, string $reasonKey): void
    {
        $linkCount = $this->countLinks($content);
        $maxLinks = (int) $this->settings->get('hardened-stacks-spam-protection.max_links', 0);

        if ($maxLinks > 0 && $linkCount > $maxLinks) {
            throw new PermissionDeniedException($reasonKey.'.too_many_links');
        }

        if (! $this->isNewUser($actor)) {
            return;
        }

        $newUserMaxLinks = (int) $this->settings->get('hardened-stacks-spam-protection.new_user_max_links', 0);
        if ($newUserMaxLinks > 0 && $linkCount > $newUserMaxLinks) {
            throw new PermissionDeniedException($reasonKey.'.too_many_links_new_user');
        }
    }

    private function assertLinkContext(User $actor, string $content, string $reasonKey): void
    {
        if (! $this->isNewUser($actor)) {
            return;
        }

        $linkCount = $this->countLinks($content);
        $threshold = (int) $this->settings->get('hardened-stacks-spam-protection.min_links_for_context_check', 4);
        if ($threshold <= 0 || $linkCount < $threshold) {
            return;
        }

        $minChars = (int) $this->settings->get('hardened-stacks-spam-protection.min_non_link_chars', 25);
        if ($minChars <= 0) {
            return;
        }

        if ($this->nonLinkTextLength($content) < $minChars) {
            throw new PermissionDeniedException($reasonKey.'.link_only_post');
        }
    }

    private function assertOptionalUrlRatio(User $actor, string $content, string $reasonKey): void
    {
        if (! $this->isNewUser($actor)) {
            return;
        }

        $maxRatio = (int) $this->settings->get('hardened-stacks-spam-protection.max_url_ratio', 0);
        if ($maxRatio <= 0 || $maxRatio >= 100) {
            return;
        }

        $plain = trim(strip_tags($content));
        $length = strlen($plain);
        $minLength = (int) $this->settings->get('hardened-stacks-spam-protection.url_ratio_min_length', 120);
        if ($length < $minLength) {
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
        $newUserDays = (int) $this->settings->get('hardened-stacks-spam-protection.new_user_days', 14);
        $newUserPostCount = (int) $this->settings->get('hardened-stacks-spam-protection.new_user_post_count', 10);

        if ($newUserDays > 0 && $actor->joined_at && $actor->joined_at->diffInDays(now()) < $newUserDays) {
            return true;
        }

        if ($newUserPostCount > 0 && (int) $actor->posts_count < $newUserPostCount) {
            return true;
        }

        return false;
    }

    private function normalizeContent(string $content): string
    {
        $plain = strtolower(trim(strip_tags($content)));
        $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;

        return $plain;
    }

    private function nonLinkTextLength(string $content): int
    {
        $plain = strip_tags($content);
        $plain = preg_replace('/https?:\/\/\S+/i', ' ', $plain) ?? $plain;
        $plain = preg_replace('/\bwww\.\S+/i', ' ', $plain) ?? $plain;
        $plain = preg_replace('/\[[^\]]*\]\([^)]+\)/', ' ', $plain) ?? $plain;
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? $plain);

        return strlen($plain);
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

    private function rateLimitKey(User $actor): string
    {
        return 'pmg_spam_last_post_'.$actor->id;
    }

    private function burstKey(User $actor): string
    {
        return 'pmg_spam_hour_'.$actor->id;
    }

    private function duplicateKey(User $actor, string $hash): string
    {
        return 'pmg_spam_dup_'.$actor->id.'_'.$hash;
    }
}
