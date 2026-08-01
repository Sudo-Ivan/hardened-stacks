<?php

namespace HardenedStacks\UserManagement\Service;

use Flarum\Discussion\Command\DeleteDiscussion;
use Flarum\Post\Command\DeletePost;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\ConnectionInterface;

class ContentManager
{
    public function __construct(
        private Dispatcher $bus,
        private ConnectionInterface $db,
        private FilesystemFactory $filesystem
    ) {
    }

    public function listPosts(User $target, int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $rows = $this->db->table('posts')
            ->join('discussions', 'discussions.id', '=', 'posts.discussion_id')
            ->where('posts.user_id', $target->id)
            ->whereIn('posts.type', ['comment', 'discussion'])
            ->whereNull('posts.deleted_at')
            ->orderByDesc('posts.created_at')
            ->offset($offset)
            ->limit($limit)
            ->get([
                'posts.id',
                'posts.discussion_id',
                'posts.number',
                'posts.type',
                'posts.content',
                'posts.created_at',
                'discussions.title as discussion_title',
            ]);

        return $rows->map(function ($row) {
            $preview = strip_tags((string) $row->content);
            $preview = preg_replace('/\s+/', ' ', $preview) ?? $preview;

            return [
                'id' => (int) $row->id,
                'discussionId' => (int) $row->discussion_id,
                'discussionTitle' => (string) $row->discussion_title,
                'number' => (int) $row->number,
                'type' => (string) $row->type,
                'preview' => mb_substr(trim($preview), 0, 160),
                'createdAt' => (string) $row->created_at,
            ];
        })->all();
    }

    public function purgeAll(User $actor, User $target, bool $hard = false): int
    {
        $postIds = $this->db->table('posts')
            ->where('user_id', $target->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        return $this->deletePosts($actor, $target, $postIds, $hard);
    }

    public function deletePosts(User $actor, User $target, array $postIds, bool $hard = false): int
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

    public function resetAvatar(User $target): void
    {
        $path = $target->attributes['avatar_url'] ?? null;

        if ($path && strpos((string) $path, '://') === false) {
            try {
                $disk = $this->filesystem->disk('flarum-avatars');
                if ($disk->has($path)) {
                    $disk->delete($path);
                }
            } catch (\Throwable) {
            }
        }

        $target->changeAvatarPath(null);
        $target->save();
    }

    public function resetProfile(User $target): void
    {
        $this->resetAvatar($target);
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
