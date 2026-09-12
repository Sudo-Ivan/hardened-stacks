<?php

use Flarum\Extend;
use HardenedStacks\Lxmf\Api\Serializer\AddLxmfUserAttributes;
use HardenedStacks\Lxmf\Listener\ApplyNoEmailRegistration;
use HardenedStacks\Lxmf\Listener\SaveLxmfAddress;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Locales(__DIR__.'/resources/locale')),

    (new Extend\ApiSerializer(\Flarum\Api\Serializer\UserSerializer::class))
        ->attributes(AddLxmfUserAttributes::class),

    (new Extend\Settings())
        ->default('hardened-stacks-lxmf.no_email_registration', '1')
        ->default('hardened-stacks-lxmf.email_domain', 'noreply.invalid')
        ->serializeToForum('hardenedStacksLxmfNoEmail', 'hardened-stacks-lxmf.no_email_registration', 'boolval'),

    (new Extend\Event())
        ->listen(\Flarum\User\Event\Saving::class, ApplyNoEmailRegistration::class)
        ->listen(\Flarum\User\Event\Saving::class, SaveLxmfAddress::class),
];
