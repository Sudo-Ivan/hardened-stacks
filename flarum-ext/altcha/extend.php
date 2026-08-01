<?php

use Flarum\Extend;
use Flarum\User\ForgotPasswordValidator;
use Flarum\User\LogInValidator;
use HardenedStacks\Altcha\Api\Controller\ChallengeController;
use HardenedStacks\Altcha\Listener\AddAltchaForumAttributes;
use HardenedStacks\Altcha\Listener\AddAltchaValidatorRule;
use HardenedStacks\Altcha\Listener\ValidatePostAltcha;
use HardenedStacks\Altcha\Listener\ValidateRegistrationAltcha;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Locales(__DIR__.'/resources/locale')),

    (new Extend\Routes('api'))
        ->get('/altcha/challenge', 'pmg.altcha.challenge', ChallengeController::class),

    (new Extend\Settings())
        ->default('hardened-stacks-altcha.enabled', '1')
        ->default('hardened-stacks-altcha.cost', 5000)
        ->default('hardened-stacks-altcha.protect_registration', '1')
        ->default('hardened-stacks-altcha.protect_login', '0')
        ->default('hardened-stacks-altcha.protect_password_reset', '1')
        ->default('hardened-stacks-altcha.protect_discussion', '0')
        ->default('hardened-stacks-altcha.protect_reply', '0')
        ->serializeToForum('hardened-stacks-altcha.protectRegistration', 'hardened-stacks-altcha.protect_registration', 'boolval')
        ->serializeToForum('hardened-stacks-altcha.protectLogin', 'hardened-stacks-altcha.protect_login', 'boolval')
        ->serializeToForum('hardened-stacks-altcha.protectForgot', 'hardened-stacks-altcha.protect_password_reset', 'boolval')
        ->serializeToForum('hardened-stacks-altcha.protectDiscussion', 'hardened-stacks-altcha.protect_discussion', 'boolval')
        ->serializeToForum('hardened-stacks-altcha.protectReply', 'hardened-stacks-altcha.protect_reply', 'boolval'),

    (new Extend\ApiSerializer(\Flarum\Api\Serializer\ForumSerializer::class))
        ->attributes(AddAltchaForumAttributes::class),

    (new Extend\Validator(LogInValidator::class))
        ->configure(AddAltchaValidatorRule::class),

    (new Extend\Validator(ForgotPasswordValidator::class))
        ->configure(AddAltchaValidatorRule::class),

    (new Extend\Event())
        ->listen(\Flarum\User\Event\Saving::class, ValidateRegistrationAltcha::class)
        ->listen(\Flarum\Post\Event\Saving::class, ValidatePostAltcha::class),
];
