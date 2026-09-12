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
use RuntimeException;
use Throwable;

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

        if ((int) $actor->id === (int) $target->id) {
            throw new PermissionDeniedException('You cannot delete your own account.');
        }

        if ((int) $target->id === 1) {
            throw new PermissionDeniedException('The root admin cannot be deleted.');
        }

        $deleted = 0;

        try {
            $this->db->transaction(function () use ($actor, $target, $purgeFirst, $hard, &$deleted): void {
                if ($purgeFirst) {
                    $deleted = $this->purgeAll($actor, $target, $hard);
                }

                $this->bus->dispatch(new DeleteUser($target->id, $actor, []));
            });
        } catch (PermissionDeniedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to delete user '.$userId.': '.$e->getMessage(), 0, $e);
        }

        return ['deleted' => $deleted, 'userDeleted' => true];
    }

    private function purgeAll(User $actor, User $target, bool $hard): int
    {
        $postIds = $this->db->table('posts')
            ->where('user_id', $target->id)
            ->when(
                $hard === false && $this->db->getSchemaBuilder()->hasColumn('posts', 'hidden_at'),
                fn ($query) => $query->whereNull('hidden_at')
            )
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return $this->deletePosts($actor, $target, $postIds, $hard);
    }

    private function deletePosts(User $actor, User $target, array $postIds, bool $hard): int
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        $deleted = 0;
        $removedDiscussionIds = [];

        foreach ($postIds as $postId) {
            if ($postId <= 0) {
                continue;
            }

            $post = Post::query()->find($postId);
            if (! $post || (int) $post->user_id !== (int) $target->id) {
                continue;
            }

            if (
                $post instanceof CommentPost
                && (int) $post->number === 1
                && isset($removedDiscussionIds[(int) $post->discussion_id])
            ) {
                continue;
            }

            if ($hard) {
                if ($post instanceof CommentPost && (int) $post->number === 1) {
                    $discussionId = (int) $post->discussion_id;
                    $this->bus->dispatch(new DeleteDiscussion($discussionId, $actor, []));
                    $removedDiscussionIds[$discussionId] = true;
                } else {
                    $this->bus->dispatch(new DeletePost($post->id, $actor, []));
                }
            } else {
                $this->softDeletePost($actor, $post);
            }

            $deleted++;
        }

        $target->refresh();
        if ($target->exists) {
            $target->refreshCommentCount();
            $target->refreshDiscussionCount();
            $target->save();
        }

        return $deleted;
    }

    private function softDeletePost(User $actor, Post $post): void
    {
        if (! $post instanceof CommentPost) {
            return;
        }

        if ($post->hidden_at) {
            return;
        }

        $post->hide($actor);
        $post->save();
    }
}
