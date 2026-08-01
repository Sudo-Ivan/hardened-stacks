<?php

namespace HardenedStacks\UserManagement\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\UserRepository;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\UserManagement\Service\ContentManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListUserPostsController implements RequestHandlerInterface
{
    public function __construct(
        private UserRepository $users,
        private ContentManager $content
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('pmg.moderateUsers');

        $userId = (int) Arr::get($request->getQueryParams(), 'id');
        $limit = (int) Arr::get($request->getQueryParams(), 'limit', 25);
        $offset = (int) Arr::get($request->getQueryParams(), 'offset', 0);

        $target = $this->users->findOrFail($userId, $actor);

        return new JsonResponse([
            'data' => $this->content->listPosts($target, $limit, $offset),
        ]);
    }
}
