<?php

namespace HardenedStacks\Altcha\Listener;

use HardenedStacks\Altcha\Service\AltchaService;

class SerializeAltchaConfigured
{
    public function __construct(
        private AltchaService $altcha
    ) {
    }

    public function __invoke($value): bool
    {
        return $this->altcha->isConfigured();
    }
}
