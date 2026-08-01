<?php

namespace HardenedStacks\UserManagement\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\UserManagement\Model\ModerationLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListModerationLogController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $limit = max(1, min(100, (int) Arr::get($request->getQueryParams(), 'limit', 25)));
        $offset = max(0, (int) Arr::get($request->getQueryParams(), 'offset', 0));

        $rows = ModerationLog::query()
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return new JsonResponse([
            'data' => $rows->map(function (ModerationLog $row) {
                return [
                    'id' => $row->id,
                    'actorId' => $row->actor_id,
                    'targetUserId' => $row->target_user_id,
                    'action' => $row->action,
                    'details' => $row->details ? json_decode($row->details, true) : null,
                    'createdAt' => optional($row->created_at)->toIso8601String(),
                ];
            })->all(),
        ]);
    }
}
