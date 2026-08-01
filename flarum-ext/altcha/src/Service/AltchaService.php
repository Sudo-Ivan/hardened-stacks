<?php

namespace HardenedStacks\Altcha\Service;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

class AltchaService
{
    private ?Altcha $client = null;

    public function __construct(
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->getHmacSecret() !== '';
    }

    public function isEnabled(): bool
    {
        return $this->isConfigured()
            && (bool) (int) $this->settings->get('hardened-stacks-altcha.enabled', 1);
    }

    public function protects(string $action): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return (bool) (int) $this->settings->get('hardened-stacks-altcha.protect_'.$action, 0);
    }

    public function shouldBypass(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function createChallenge(): array
    {
        $pbkdf2 = new Pbkdf2();
        $challenge = $this->client()->createChallenge(new CreateChallengeOptions(
            algorithm: $pbkdf2,
            cost: $this->cost(),
            counter: random_int(5000, 10000),
            expiresAt: time() + 600,
        ));

        return $challenge->toArray();
    }

    public function verify(?string $payload): bool
    {
        if (! $this->isConfigured() || ! is_string($payload) || $payload === '') {
            return false;
        }

        $result = $this->client()->verifySolution(new VerifySolutionOptions(
            payload: $payload,
            algorithm: new Pbkdf2(),
        ));

        return $result->verified && ! $result->expired;
    }

    private function cost(): int
    {
        $cost = (int) $this->settings->get('hardened-stacks-altcha.cost', 5000);

        return max(1000, min(50000, $cost));
    }

    private function getHmacSecret(): string
    {
        $env = getenv('ALTCHA_HMAC_SECRET');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        return (string) $this->settings->get('hardened-stacks-altcha.hmac_secret', '');
    }

    private function client(): Altcha
    {
        if ($this->client === null) {
            $this->client = new Altcha(hmacSignatureSecret: $this->getHmacSecret());
        }

        return $this->client;
    }
}
