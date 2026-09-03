<?php

use Flarum\Extend;
use HardenedStacks\SpamProtection\Listener\MonitorNewUser;
use HardenedStacks\SpamProtection\Listener\MonitorPostedContent;

return [
    (new Extend\Locales(__DIR__.'/resources/locale')),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Event())
        ->listen(\Flarum\Post\Event\Posted::class, MonitorPostedContent::class)
        ->listen(\Flarum\User\Event\Registered::class, MonitorNewUser::class),

    (new Extend\Settings())
        ->default('hardened-stacks-spam-protection.enabled', '0')
        ->default('hardened-stacks-spam-protection.base_url', 'https://openrouter.ai/api/v1')
        ->default('hardened-stacks-spam-protection.model', 'openai/gpt-4o-mini')
        ->default('hardened-stacks-spam-protection.monitor_posts', '1')
        ->default('hardened-stacks-spam-protection.monitor_new_users', '1')
        ->default('hardened-stacks-spam-protection.new_user_days', 14)
        ->default('hardened-stacks-spam-protection.new_user_post_count', 10)
        ->default('hardened-stacks-spam-protection.min_confidence', 70)
        ->default('hardened-stacks-spam-protection.action_hide_post', '1')
        ->default('hardened-stacks-spam-protection.action_hide_discussion', '1')
        ->default('hardened-stacks-spam-protection.action_lock_discussion', '1')
        ->default('hardened-stacks-spam-protection.action_suspend_user', '1')
        ->default('hardened-stacks-spam-protection.suspend_days', 30)
        ->default('hardened-stacks-spam-protection.fail_open', '1'),
];
