<?php

namespace HardenedStacks\UserManagement\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\UserManagement\Service\UserModerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ModerateUserController implements RequestHandlerInterface
{
    public function __construct(
        private UserModerator $moderator
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $userId = (int) Arr::get($request->getQueryParams(), 'id');

        $body = json_decode((string) $request->getBody(), true) ?? [];
        $attributes = Arr::get($body, 'data.attributes', []);

        $result = $this->moderator->moderate($actor, $userId, $attributes);

        return new JsonResponse(['data' => $result]);
    }
}
