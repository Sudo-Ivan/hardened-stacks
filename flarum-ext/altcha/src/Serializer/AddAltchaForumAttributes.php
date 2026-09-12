<?php

namespace HardenedStacks\Altcha\Serializer;

use Flarum\Api\Serializer\ForumSerializer;
use HardenedStacks\Altcha\Service\AltchaService;

class AddAltchaForumAttributes
{
    public function __construct(
        private AltchaService $altcha
    ) {
    }

    public function __invoke(ForumSerializer $serializer, $model, array $attributes): array
    {
        $attributes['hardened-stacks-altcha.configured'] = $this->altcha->isConfigured();

        return $attributes;
    }
}
