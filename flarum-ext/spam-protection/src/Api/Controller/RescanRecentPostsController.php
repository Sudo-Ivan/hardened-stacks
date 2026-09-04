<?php

namespace HardenedStacks\SpamProtection\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Post\CommentPost;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\SpamProtection\SpamMonitor;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RescanRecentPostsController implements RequestHandlerInterface
{
    public function __construct(
        private SpamMonitor $monitor
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        if (! $this->monitor->isEnabled()) {
            return new JsonResponse([
                'data' => [
                    'scanned' => 0,
                    'spam' => 0,
                    'message' => 'AI spam protection is disabled or not configured.',
                ],
            ]);
        }

        $body = json_decode((string) $request->getBody(), true) ?? [];
        $limit = (int) Arr::get($body, 'data.attributes.limit', 25);
        $limit = max(1, min(100, $limit));

        $posts = CommentPost::query()
            ->where('type', 'comment')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $scanned = 0;
        $spam = 0;

        foreach ($posts as $post) {
            if (! $post instanceof CommentPost) {
                continue;
            }

            $verdict = $this->monitor->reviewPost($post, 'rescan');
            $scanned++;
            if ($verdict->isSpam) {
                $spam++;
            }
        }

        return new JsonResponse([
            'data' => [
                'scanned' => $scanned,
                'spam' => $spam,
            ],
        ]);
    }
}
