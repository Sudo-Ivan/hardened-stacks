<?php

namespace HardenedStacks\DeleteUsers\Service;

use Flarum\Discussion\Command\DeleteDiscussion;
use Flarum\Post\Command\DeletePost;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\User\Command\DeleteUser;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class UserDeleter
{
    public function __construct(
        private Dispatcher $bus,
        private ConnectionInterface $db
    ) {
    }

    public function delete(User $actor, int $userId, bool $purgeFirst, bool $hard): array
    {
        $target = User::query()->findOrFail($userId);

        $actor->assertCan('pmg.deleteUsers');

        if ($target->isAdmin()) {
            throw new PermissionDeniedException('Administrators cannot be deleted.');
        }

        if ($actor->id === $target->id) {
            throw new PermissionDeniedException('You cannot delete your own account.');
        }

        $deleted = 0;
        if ($purgeFirst) {
            $deleted = $this->purgeAll($actor, $target, $hard);
        }

        $this->bus->dispatch(new DeleteUser($target->id, $actor, []));

        return ['deleted' => $deleted, 'userDeleted' => true];
    }

    private function purgeAll(User $actor, User $target, bool $hard): int
    {
        $postIds = $this->db->table('posts')
            ->where('user_id', $target->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        return $this->deletePosts($actor, $target, $postIds, $hard);
    }

    private function deletePosts(User $actor, User $target, array $postIds, bool $hard): int
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        $deleted = 0;

        foreach ($postIds as $postId) {
            if ($postId <= 0) {
                continue;
            }

            $post = Post::query()->find($postId);
            if (! $post || (int) $post->user_id !== (int) $target->id) {
                continue;
            }

            if ($hard) {
                $this->hardDeletePost($actor, $post);
            } else {
                $this->softDeletePost($actor, $post);
            }

            $deleted++;
        }

        $target->refreshCommentCount();
        $target->refreshDiscussionCount();
        $target->save();

        return $deleted;
    }

    private function softDeletePost(User $actor, Post $post): void
    {
        if ($post->hidden_at) {
            return;
        }

        $post->hide($actor);
        $post->save();
    }

    private function hardDeletePost(User $actor, Post $post): void
    {
        if ($post instanceof CommentPost && (int) $post->number === 1) {
            $this->bus->dispatch(new DeleteDiscussion($post->discussion_id, $actor, []));

            return;
        }

        $this->bus->dispatch(new DeletePost($post->id, $actor, []));
    }
}
