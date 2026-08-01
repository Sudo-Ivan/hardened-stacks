<?php

namespace HardenedStacks\UserManagement\Api\Serializer;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;

class AddUserModerationAttributes
{
    public function __invoke(UserSerializer $serializer, User $user, array $attributes): array
    {
        $attributes['canPmgModerate'] = false;
        $attributes['canPmgPurgeContent'] = false;
        $attributes['canPmgDeleteUser'] = false;

        $actor = $serializer->getActor();

        if ($actor->isGuest() || (int) $actor->id === (int) $user->id) {
            return $attributes;
        }

        if (! $actor->hasPermission('pmg.moderateUsers') || $user->isAdmin()) {
            return $attributes;
        }

        $attributes['canPmgModerate'] = true;
        $attributes['canPmgPurgeContent'] = $actor->hasPermission('pmg.purgeContent');
        $attributes['canPmgDeleteUser'] = $actor->hasPermission('pmg.deleteUsers');
        $attributes['pmgPostingLocked'] = (bool) $user->getPreference('pmgPostingLocked');
        $attributes['pmgSuspended'] = $this->isSuspended($user);
        $attributes['pmgSuspendedUntil'] = (string) ($user->getPreference('pmgSuspendedUntil') ?? '');

        $message = trim((string) $user->getPreference('pmgPostingLockMessage', ''));
        if ($message !== '') {
            $attributes['pmgPostingLockMessage'] = $message;
        }

        return $attributes;
    }

    private function isSuspended(User $user): bool
    {
        $until = $user->getPreference('pmgSuspendedUntil');

        if (! is_string($until) || $until === '') {
            return false;
        }

        if ($until === 'forever') {
            return true;
        }

        try {
            return \Carbon\Carbon::parse($until)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }
}
