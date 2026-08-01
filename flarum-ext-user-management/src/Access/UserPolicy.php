<?php

namespace HardenedStacks\UserManagement\Access;

use Flarum\User\User;

class UserPolicy
{
    public function delete(User $actor, User $user)
    {
        if ($actor->hasPermission('pmg.deleteUsers')) {
            return true;
        }
    }
}
