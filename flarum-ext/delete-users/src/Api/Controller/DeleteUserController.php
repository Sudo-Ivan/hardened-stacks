<?php

namespace HardenedStacks\DeleteUsers\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\DeleteUsers\Service\UserDeleter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DeleteUserController implements RequestHandlerInterface
{
    public function __construct(
        private UserDeleter $deleter
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $userId = (int) Arr::get($request->getQueryParams(), 'id');

        $body = json_decode((string) $request->getBody(), true) ?? [];
        $attributes = Arr::get($body, 'data.attributes', []);

        $result = $this->deleter->delete(
            $actor,
            $userId,
            (bool) Arr::get($attributes, 'purgeFirst', true),
            (bool) Arr::get($attributes, 'hard', false)
        );

        return new JsonResponse(['data' => $result]);
    }
}
