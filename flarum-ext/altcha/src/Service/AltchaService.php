<?php

namespace HardenedStacks\Altcha\Service;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeOptions;
use DateInterval;
use DateTimeImmutable;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Throwable;

class AltchaService
{
    private ?Altcha $client = null;

    public function __construct(
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->ensureHmacSecret() !== '';
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
        $maxNumber = $this->maxNumber();
        $challenge = $this->client()->createChallenge(new ChallengeOptions(
            maxNumber: $maxNumber,
            expires: (new DateTimeImmutable())->add(new DateInterval('PT10M')),
        ));

        return $this->challengeToArray($challenge);
    }

    public function verify(?string $payload): bool
    {
        if (! $this->isConfigured() || ! is_string($payload) || $payload === '') {
            return false;
        }

        try {
            return $this->client()->verifySolution($payload, true);
        } catch (Throwable) {
            return false;
        }
    }

    private function maxNumber(): int
    {
        $max = (int) $this->settings->get('hardened-stacks-altcha.cost', 50000);

        return max(1000, min(1000000, $max));
    }

    private function challengeToArray(Challenge $challenge): array
    {
        return [
            'algorithm' => $challenge->algorithm,
            'challenge' => $challenge->challenge,
            'maxnumber' => $challenge->maxNumber,
            'maxNumber' => $challenge->maxNumber,
            'salt' => $challenge->salt,
            'signature' => $challenge->signature,
        ];
    }

    private function ensureHmacSecret(): string
    {
        $env = getenv('ALTCHA_HMAC_SECRET');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        $secret = (string) $this->settings->get('hardened-stacks-altcha.hmac_secret', '');
        if ($secret !== '') {
            return $secret;
        }

        $secret = bin2hex(random_bytes(32));
        $this->settings->set('hardened-stacks-altcha.hmac_secret', $secret);

        return $secret;
    }

    private function client(): Altcha
    {
        if ($this->client === null) {
            $this->client = new Altcha($this->ensureHmacSecret());
        }

        return $this->client;
    }
}
