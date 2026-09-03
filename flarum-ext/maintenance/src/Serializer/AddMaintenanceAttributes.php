<?php

namespace HardenedStacks\Maintenance\Serializer;

use Flarum\Api\Serializer\ForumSerializer;
use HardenedStacks\Maintenance\MaintenanceState;

class AddMaintenanceAttributes
{
    public function __construct(
        private MaintenanceState $state
    ) {
    }

    public function __invoke(ForumSerializer $serializer, $model, array $attributes): array
    {
        return array_merge($attributes, $this->state->forumAttributes($serializer->getActor()));
    }
}
