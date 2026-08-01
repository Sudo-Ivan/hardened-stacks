<?php

namespace HardenedStacks\Altcha\Listener;

use Flarum\Foundation\Event\ApplicationBooted;
use HardenedStacks\Altcha\Service\AltchaService;

class SyncConfiguredSetting
{
    public function __construct(
        private AltchaService $altcha
    ) {
    }

    public function handle(ApplicationBooted $event): void
    {
        $this->altcha->syncConfiguredFlag();
    }
}
