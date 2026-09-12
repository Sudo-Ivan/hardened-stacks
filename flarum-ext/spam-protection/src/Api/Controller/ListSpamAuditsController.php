<?php

namespace HardenedStacks\SpamProtection\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\SpamProtection\SpamMonitor;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListSpamAuditsController implements RequestHandlerInterface
{
    public function __construct(
        private SpamMonitor $monitor
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $query = $request->getQueryParams();
        $limit = isset($query['limit']) ? (int) $query['limit'] : 50;

        return new JsonResponse([
            'data' => [
                'enabled' => $this->monitor->isEnabled(),
                'configured' => $this->monitor->isConfigured(),
                'audits' => $this->monitor->audit()->recent($limit),
            ],
        ]);
    }
}
