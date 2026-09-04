<?php

namespace HardenedStacks\DeleteUsers\Api\Controller;

use Flarum\Foundation\ValidationException;
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

class DeleteUserController implements RequestHandlerInterface
{
    public function __construct(
        private UserDeleter $deleter,
        private LoggerInterface $logger
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $userId = (int) Arr::get($request->getQueryParams(), 'id');

        $body = json_decode((string) $request->getBody(), true) ?? [];
        $attributes = Arr::get($body, 'data.attributes', []);

        try {
            $result = $this->deleter->delete(
                $actor,
                $userId,
                (bool) Arr::get($attributes, 'purgeFirst', true),
                (bool) Arr::get($attributes, 'hard', true)
            );
        } catch (PermissionDeniedException $e) {
            throw $e;
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('hardened-stacks-delete-users: delete failed', [
                'user_id' => $userId,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            throw new ValidationException([
                'user' => ['Delete failed: '.$e->getMessage()],
            ]);
        }

        return new JsonResponse(['data' => $result]);
    }
}
