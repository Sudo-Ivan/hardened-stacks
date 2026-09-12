<?php

namespace HardenedStacks\Altcha\Listener;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\User\Event\Saving;
use HardenedStacks\Altcha\Service\AltchaService;
use HardenedStacks\Altcha\Support\AltchaTokenExtractor;

class ValidateRegistrationAltcha
{
    public function __construct(
        private AltchaService $altcha,
        private Translator $translator
    ) {
    }

    public function handle(Saving $event): void
    {
        if ($event->user->exists || ! $this->altcha->protects('registration')) {
            return;
        }

        if ($this->altcha->shouldBypass($event->actor)) {
            return;
        }

        if (AltchaTokenExtractor::usesOAuthRegistrationToken($event->data)) {
            return;
        }

        $token = AltchaTokenExtractor::fromData($event->data);
        if (! $this->altcha->verify($token)) {
            throw new ValidationException([
                'captchaToken' => [$this->translator->trans('hardened-stacks-altcha.validation.failed')],
            ]);
        }
    }
}
