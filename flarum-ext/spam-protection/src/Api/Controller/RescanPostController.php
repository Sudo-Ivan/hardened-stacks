<?php

namespace HardenedStacks\SpamProtection\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Post\CommentPost;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\SpamProtection\SpamMonitor;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RescanPostController implements RequestHandlerInterface
{
    public function __construct(
        private SpamMonitor $monitor
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $postId = (int) Arr::get($request->getQueryParams(), 'id');
        $post = CommentPost::query()->find($postId);
        if (! $post instanceof CommentPost) {
            throw new ModelNotFoundException();
        }

        $verdict = $this->monitor->reviewPost($post, 'rescan');

        return new JsonResponse([
            'data' => [
                'postId' => (int) $post->id,
                'isSpam' => $verdict->isSpam,
                'confidence' => $verdict->confidence,
                'actions' => $verdict->actions,
                'reason' => $verdict->reason,
            ],
        ]);
    }
}
