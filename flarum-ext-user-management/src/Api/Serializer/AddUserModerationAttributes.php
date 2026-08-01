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
        $attributes['canPmgModerate'] = false;
        $attributes['canPmgPurgeContent'] = false;
        $attributes['canPmgDeleteUser'] = false;

        $actor = $serializer->getActor();

        if ($actor->isGuest()) {
            return $attributes;
        }

        $actorId = (int) $actor->id;
        $userId = (int) $user->id;
        $canModerate = $actor->hasPermission('pmg.moderateUsers');

        if ($actorId === $userId || $canModerate) {
            $attributes['pmgPostingLocked'] = $this->moderator->isPostingLocked($user);
            $attributes['pmgPostingLockMessage'] = $this->moderator->postingLockMessage($user);
            $attributes['pmgSuspended'] = $this->moderator->isSuspended($user);
            $attributes['pmgSuspendedUntil'] = $user->getPreference('pmgSuspendedUntil');
        }

        if ($canModerate && $actorId !== $userId && ! $user->isAdmin()) {
            $attributes['canPmgModerate'] = true;
            $attributes['canPmgPurgeContent'] = $actor->hasPermission('pmg.purgeContent');
            $attributes['canPmgDeleteUser'] = $actor->hasPermission('pmg.deleteUsers');
        }

        return $attributes;
    }
}
