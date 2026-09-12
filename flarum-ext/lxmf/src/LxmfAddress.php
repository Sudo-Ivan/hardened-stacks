<?php

namespace HardenedStacks\Lxmf;

class LxmfAddress
{
    public static function normalize(string $raw): string
    {
        $value = strtolower(trim($raw));

        return (string) preg_replace('/[^0-9a-f]/', '', $value);
    }

    public static function isValid(string $normalized): bool
    {
        return (bool) preg_match('/^[0-9a-f]{32}$/', $normalized);
    }
}
