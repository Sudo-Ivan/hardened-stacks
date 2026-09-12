<?php

namespace HardenedStacks\DeleteUsers\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class UserPolicy extends AbstractPolicy
{
    public function delete(User $actor, User $user)
    {
        return $actor->can('pmg.deleteUsers')
            && ! $user->isAdmin()
            && $actor->id !== $user->id;
    }
}
