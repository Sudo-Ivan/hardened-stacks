<?php

namespace HardenedStacks\DeleteUsers\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\DeleteUsers\Service\UserDeleter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class BulkDeleteUsersController implements RequestHandlerInterface
{
    public function __construct(
        private UserDeleter $deleter,
        private LoggerInterface $logger
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $body = json_decode((string) $request->getBody(), true) ?? [];
        $attributes = Arr::get($body, 'data.attributes', []);

        $userIds = Arr::get($attributes, 'userIds', []);
        if (! is_array($userIds)) {
            $userIds = [];
        }

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $purgeFirst = (bool) Arr::get($attributes, 'purgeFirst', true);
        $hard = (bool) Arr::get($attributes, 'hard', true);

        $deletedUsers = 0;
        $deletedPosts = 0;
        $skipped = [];

        foreach ($userIds as $userId) {
            if ($userId <= 0) {
                continue;
            }

            try {
                $result = $this->deleter->delete($actor, $userId, $purgeFirst, $hard);
                $deletedUsers++;
                $deletedPosts += (int) ($result['deleted'] ?? 0);
            } catch (PermissionDeniedException $e) {
                $skipped[] = [
                    'id' => $userId,
                    'reason' => $e->getMessage() !== '' ? $e->getMessage() : 'Permission denied.',
                ];
            } catch (ModelNotFoundException $e) {
                $skipped[] = [
                    'id' => $userId,
                    'reason' => 'User not found.',
                ];
            } catch (Throwable $e) {
                $this->logger->error('hardened-stacks-delete-users: bulk delete item failed', [
                    'user_id' => $userId,
                    'actor_id' => $actor->id,
                    'error' => $e->getMessage(),
                ]);

                $skipped[] = [
                    'id' => $userId,
                    'reason' => $e->getMessage() !== '' ? $e->getMessage() : 'Delete failed.',
                ];
            }
        }

        return new JsonResponse([
            'data' => [
                'deletedUsers' => $deletedUsers,
                'deletedPosts' => $deletedPosts,
                'skipped' => $skipped,
            ],
        ]);
    }
}
