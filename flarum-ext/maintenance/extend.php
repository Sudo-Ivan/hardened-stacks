<?php

use Flarum\Extend;
use HardenedStacks\Maintenance\Listener\BlockWriteOperations;
use HardenedStacks\Maintenance\Middleware\MaintenanceMiddleware;
use HardenedStacks\Maintenance\Serializer\AddMaintenanceAttributes;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Locales(__DIR__.'/resources/locale')),

    (new Extend\Middleware('api'))
        ->add(MaintenanceMiddleware::class),

    (new Extend\Middleware('forum'))
        ->add(MaintenanceMiddleware::class),

    (new Extend\ApiSerializer(\Flarum\Api\Serializer\ForumSerializer::class))
        ->attributes(AddMaintenanceAttributes::class),

    (new Extend\Event())
        ->listen(\Flarum\Post\Event\Saving::class, BlockWriteOperations::class)
        ->listen(\Flarum\Discussion\Event\Saving::class, BlockWriteOperations::class)
        ->listen(\Flarum\User\Event\Saving::class, BlockWriteOperations::class),

    (new Extend\Settings())
        ->default('hardened-stacks-maintenance.mode', 'off')
        ->default('hardened-stacks-maintenance.title', 'Forum under maintenance')
        ->default('hardened-stacks-maintenance.message', 'We are performing maintenance. Please check back soon.')
        ->default('hardened-stacks-maintenance.allow_login', '1')
        ->default('hardened-stacks-maintenance.show_banner', '1'),
];
