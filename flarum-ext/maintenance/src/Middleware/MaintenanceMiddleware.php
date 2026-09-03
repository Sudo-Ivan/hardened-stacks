<?php

namespace HardenedStacks\Maintenance\Middleware;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\Maintenance\MaintenanceState;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MaintenanceMiddleware implements MiddlewareInterface
{
    /**
     * Route names always allowed for guests during maintenance.
     *
     * @var list<string>
     */
    private const ALWAYS_ALLOWED = [
        'forum.show',
    ];

    /**
     * Auth-related routes allowed when login is enabled.
     *
     * @var list<string>
     */
    private const AUTH_ALLOWED = [
        'token',
        'forgot',
    ];

    public function __construct(
        private MaintenanceState $state
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->state->isActive()) {
            return $handler->handle($request);
        }

        $actor = RequestUtil::getActor($request);
        if ($this->state->isExempt($actor)) {
            return $handler->handle($request);
        }

        $routeName = (string) $request->getAttribute('routeName');
        if ($this->isAllowedRoute($routeName)) {
            return $handler->handle($request);
        }

        $method = strtoupper($request->getMethod());
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if ($this->state->isClosed()) {
            return $this->denied();
        }

        if ($this->state->isReadOnly() && $isWrite) {
            return $this->denied();
        }

        return $handler->handle($request);
    }

    private function isAllowedRoute(string $routeName): bool
    {
        if (in_array($routeName, self::ALWAYS_ALLOWED, true)) {
            return true;
        }

        if ($this->state->allowLogin() && in_array($routeName, self::AUTH_ALLOWED, true)) {
            return true;
        }

        return false;
    }

    private function denied(): ResponseInterface
    {
        return new JsonResponse([
            'errors' => [[
                'status' => '503',
                'code' => 'maintenance',
                'title' => $this->state->title(),
                'detail' => $this->state->message(),
            ]],
        ], 503);
    }
}
