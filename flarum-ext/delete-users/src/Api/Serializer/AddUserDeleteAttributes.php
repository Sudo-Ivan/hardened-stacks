<?php

namespace HardenedStacks\DeleteUsers\Api\Serializer;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;

class AddUserDeleteAttributes
{
    public function __invoke(UserSerializer $serializer, User $user, array $attributes): array
    {
        $actor = $serializer->getActor();

        $attributes['canPmgDelete'] = $actor->can('pmg.deleteUsers')
            && ! $user->isAdmin()
            && $actor->id !== $user->id;

        return $attributes;
    }
}
