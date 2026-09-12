<?php

namespace HardenedStacks\Lxmf\Api\Serializer;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;

class AddLxmfUserAttributes
{
    public function __invoke(UserSerializer $serializer, User $user, array $attributes): array
    {
        $actor = $serializer->getActor();

        if ($actor->isGuest() || ! $user->lxmf_address) {
            return $attributes;
        }

        $attributes['lxmfAddress'] = $user->lxmf_address;

        return $attributes;
    }
}
