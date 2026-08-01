<?php

namespace HardenedStacks\UserManagement\Api\Serializer;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;
use HardenedStacks\UserManagement\Service\UserModerator;

class AddUserModerationAttributes
{
    public function __construct(
        private UserModerator $moderator
    ) {
    }

    public function __invoke(UserSerializer $serializer, User $user, array $attributes): array
    {
        $actor = $serializer->getActor();

        if ($actor->id === $user->id || $actor->can('pmg.moderateUsers')) {
            $attributes['pmgPostingLocked'] = $this->moderator->isPostingLocked($user);
            $attributes['pmgPostingLockMessage'] = $this->moderator->postingLockMessage($user);
            $attributes['pmgSuspended'] = $this->moderator->isSuspended($user);
            $attributes['pmgSuspendedUntil'] = $user->getPreference('pmgSuspendedUntil');
        }

        if ($actor->can('pmg.moderateUsers') && $actor->id !== $user->id && ! $user->isAdmin()) {
            $attributes['canPmgModerate'] = true;
            $attributes['canPmgPurgeContent'] = $actor->hasPermission('pmg.purgeContent');
            $attributes['canPmgDeleteUser'] = $actor->hasPermission('pmg.deleteUsers');
        } else {
            $attributes['canPmgModerate'] = false;
            $attributes['canPmgPurgeContent'] = false;
            $attributes['canPmgDeleteUser'] = false;
        }

        return $attributes;
    }
}
