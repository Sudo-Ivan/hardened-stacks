<?php

namespace HardenedStacks\Altcha\Listener;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\User\ForgotPasswordValidator;
use Flarum\User\LogInValidator;
use HardenedStacks\Altcha\Service\AltchaService;

class AddAltchaValidatorRule
{
    public function __construct(
        private AltchaService $altcha,
        private Translator $translator
    ) {
    }

    public function __invoke($flarumValidator, $laravelValidator): void
    {
        $laravelValidator->addExtension('pmg_altcha', function (string $attribute, mixed $value): bool {
            return is_string($value) && $this->altcha->verify($value);
        });

        if (method_exists($laravelValidator, 'setCustomMessages')) {
            $laravelValidator->setCustomMessages([
                'captchaToken.pmg_altcha' => $this->translator->trans('hardened-stacks-altcha.validation.failed'),
            ]);
        }

        if ($flarumValidator instanceof LogInValidator && $this->altcha->protects('login')) {
            $laravelValidator->addRules(['captchaToken' => ['required', 'pmg_altcha']]);
        }

        if ($flarumValidator instanceof ForgotPasswordValidator && $this->altcha->protects('password_reset')) {
            $laravelValidator->addRules(['captchaToken' => ['required', 'pmg_altcha']]);
        }
    }
}
