<?php

namespace HardenedStacks\Altcha\Support;

use Flarum\User\Exception\InvalidConfirmationTokenException;
use Flarum\User\RegistrationToken;
use Illuminate\Support\Arr;

final class AltchaTokenExtractor
{
    public static function fromData(array $data): string
    {
        $token = Arr::get($data, 'attributes.captchaToken')
            ?? Arr::get($data, 'captchaToken')
            ?? Arr::get($data, 'data.attributes.captchaToken')
            ?? Arr::get($data, 'attributes.altcha')
            ?? Arr::get($data, 'altcha');

        return is_string($token) ? $token : '';
    }

    public static function usesOAuthRegistrationToken(array $data): bool
    {
        $token = Arr::get($data, 'attributes.token')
            ?? Arr::get($data, 'token')
            ?? Arr::get($data, 'data.attributes.token');

        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            RegistrationToken::validOrFail($token);

            return true;
        } catch (InvalidConfirmationTokenException) {
            return false;
        } catch (\Throwable) {
            return false;
        }
    }
}
