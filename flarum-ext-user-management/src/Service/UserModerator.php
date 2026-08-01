<?php

namespace HardenedStacks\UserManagement\Service;

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\User\Command\DeleteUser;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Flarum\User\UserRepository;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;

class UserModerator
{
    public function __construct(
        private UserRepository $users,
        private ContentManager $content,
        private AuditLogger $audit,
        private Dispatcher $bus
    ) {
    }

    public function moderate(User $actor, int $userId, array $attributes): array
    {
        $target = $this->users->findOrFail($userId);
        $action = (string) Arr::get($attributes, 'action', '');

        $this->assertCanModerateTarget($actor, $target, $action);

        return match ($action) {
            'lock_posting' => $this->lockPosting($actor, $target, (string) Arr::get($attributes, 'message', '')),
            'unlock_posting' => $this->unlockPosting($actor, $target),
            'suspend' => $this->suspend($actor, $target, Arr::get($attributes, 'until'), (string) Arr::get($attributes, 'message', '')),
            'unsuspend' => $this->unsuspend($actor, $target),
            'reset_avatar' => $this->resetAvatar($actor, $target),
            'reset_profile' => $this->resetProfile($actor, $target),
            'purge_content' => $this->purgeContent($actor, $target, (bool) Arr::get($attributes, 'hard', false)),
            'delete_posts' => $this->deletePosts($actor, $target, (array) Arr::get($attributes, 'postIds', []), (bool) Arr::get($attributes, 'hard', false)),
            'delete_user' => $this->deleteUser($actor, $target, (bool) Arr::get($attributes, 'purgeFirst', true), (bool) Arr::get($attributes, 'hard', false)),
            default => throw new ValidationException(['action' => 'Unknown moderation action.']),
        };
    }

    public function isPostingLocked(User $user): bool
    {
        return (bool) $user->getPreference('pmgPostingLocked');
    }

    public function isSuspended(User $user): bool
    {
        $until = $user->getPreference('pmgSuspendedUntil');

        if (! $until) {
            return false;
        }

        if ($until === 'forever') {
            return true;
        }

        try {
            return Carbon::parse($until)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    public function postingLockMessage(User $user): ?string
    {
        $message = trim((string) $user->getPreference('pmgPostingLockMessage', ''));

        return $message !== '' ? $message : null;
    }

    private function lockPosting(User $actor, User $target, string $message): array
    {
        $actor->assertCan('pmg.moderateUsers');

        $target->setPreference('pmgPostingLocked', true);
        $target->setPreference('pmgPostingLockMessage', $message);
        $target->save();

        $this->audit->log($actor, $target, 'lock_posting', ['message' => $message]);

        return $this->statusPayload($target);
    }

    private function unlockPosting(User $actor, User $target): array
    {
        $actor->assertCan('pmg.moderateUsers');

        $target->setPreference('pmgPostingLocked', false);
        $target->setPreference('pmgPostingLockMessage', '');
        $target->save();

        $this->audit->log($actor, $target, 'unlock_posting');

        return $this->statusPayload($target);
    }

    private function suspend(User $actor, User $target, mixed $until, string $message): array
    {
        $actor->assertCan('pmg.moderateUsers');

        $value = $this->normalizeSuspendUntil($until);
        $target->setPreference('pmgSuspendedUntil', $value);
        $target->setPreference('pmgPostingLocked', true);
        $target->setPreference('pmgPostingLockMessage', $message !== '' ? $message : 'Account suspended.');
        $target->save();

        $this->audit->log($actor, $target, 'suspend', ['until' => $value, 'message' => $message]);

        return $this->statusPayload($target);
    }

    private function unsuspend(User $actor, User $target): array
    {
        $actor->assertCan('pmg.moderateUsers');

        $target->setPreference('pmgSuspendedUntil', null);
        $target->setPreference('pmgPostingLocked', false);
        $target->setPreference('pmgPostingLockMessage', '');
        $target->save();

        $this->audit->log($actor, $target, 'unsuspend');

        return $this->statusPayload($target);
    }

    private function resetAvatar(User $actor, User $target): array
    {
        $actor->assertCan('pmg.moderateUsers');

        $this->content->resetAvatar($target);
        $this->audit->log($actor, $target, 'reset_avatar');

        return $this->statusPayload($target);
    }

    private function resetProfile(User $actor, User $target): array
    {
        $actor->assertCan('pmg.moderateUsers');

        $this->content->resetProfile($target);
        $this->audit->log($actor, $target, 'reset_profile');

        return $this->statusPayload($target);
    }

    private function purgeContent(User $actor, User $target, bool $hard): array
    {
        $actor->assertCan('pmg.purgeContent');

        $deleted = $this->content->purgeAll($actor, $target, $hard);
        $this->audit->log($actor, $target, 'purge_content', ['deleted' => $deleted, 'hard' => $hard]);

        return array_merge($this->statusPayload($target), ['deleted' => $deleted]);
    }

    private function deletePosts(User $actor, User $target, array $postIds, bool $hard): array
    {
        $actor->assertCan('pmg.purgeContent');

        $deleted = $this->content->deletePosts($actor, $target, $postIds, $hard);
        $this->audit->log($actor, $target, 'delete_posts', ['deleted' => $deleted, 'hard' => $hard, 'postIds' => $postIds]);

        return array_merge($this->statusPayload($target), ['deleted' => $deleted]);
    }

    private function deleteUser(User $actor, User $target, bool $purgeFirst, bool $hard): array
    {
        $actor->assertCan('pmg.deleteUsers');

        $deleted = 0;
        if ($purgeFirst) {
            $deleted = $this->content->purgeAll($actor, $target, $hard);
        }

        $this->audit->log($actor, $target, 'delete_user', ['purgeFirst' => $purgeFirst, 'hard' => $hard, 'deleted' => $deleted]);

        $this->bus->dispatch(new DeleteUser($target->id, $actor, []));

        return ['deleted' => $deleted, 'userDeleted' => true];
    }

    private function normalizeSuspendUntil(mixed $until): string
    {
        if ($until === null || $until === '' || $until === 'forever') {
            return 'forever';
        }

        return Carbon::parse((string) $until)->toIso8601String();
    }

    private function statusPayload(User $target): array
    {
        return [
            'postingLocked' => $this->isPostingLocked($target),
            'postingLockMessage' => $this->postingLockMessage($target),
            'suspended' => $this->isSuspended($target),
            'suspendedUntil' => $target->getPreference('pmgSuspendedUntil'),
        ];
    }

    private function assertCanModerateTarget(User $actor, User $target, string $action): void
    {
        if ($target->isAdmin()) {
            throw new PermissionDeniedException('Administrators cannot be moderated.');
        }

        if ($actor->id === $target->id) {
            throw new PermissionDeniedException('You cannot moderate your own account.');
        }

        if (in_array($action, ['purge_content', 'delete_posts', 'delete_user'], true)) {
            return;
        }

        $actor->assertCan('pmg.moderateUsers');
    }
}
