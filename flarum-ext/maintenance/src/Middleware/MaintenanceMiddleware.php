<?php

namespace HardenedStacks\Maintenance\Middleware;

use Flarum\Http\RequestUtil;
use Flarum\User\UserRepository;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\Maintenance\MaintenanceState;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MaintenanceMiddleware implements MiddlewareInterface
{
    /**
     * API route names always allowed for guests during maintenance.
     *
     * @var list<string>
     */
    private const ALWAYS_ALLOWED = [
        'forum.show',
    ];

    /**
     * Auth routes allowed in read-only mode for existing members.
     *
     * @var list<string>
     */
    private const READ_ONLY_AUTH = [
        'token',
        'forgot',
    ];

    /**
     * Closed-mode auth: admin login only when allow_login is enabled.
     *
     * @var list<string>
     */
    private const CLOSED_AUTH = [
        'token',
    ];

    public function __construct(
        private MaintenanceState $state,
        private UserRepository $users
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
        if ($this->isAllowedRoute($request, $routeName)) {
            return $handler->handle($request);
        }

        $method = strtoupper($request->getMethod());
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $isApi = $this->isApiRequest($request);

        if ($this->state->isClosed()) {
            if ($isApi || $isWrite) {
                return $this->denied();
            }

            return $handler->handle($request);
        }

        if ($this->state->isReadOnly() && $isWrite) {
            return $this->denied();
        }

        return $handler->handle($request);
    }

    private function isApiRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return str_contains($path, '/api');
    }

    private function isAllowedRoute(ServerRequestInterface $request, string $routeName): bool
    {
        if (in_array($routeName, self::ALWAYS_ALLOWED, true)) {
            return true;
        }

        if ($this->state->isReadOnly() && in_array($routeName, self::READ_ONLY_AUTH, true)) {
            return true;
        }

        if ($this->state->isClosed()
            && $this->state->allowLogin()
            && in_array($routeName, self::CLOSED_AUTH, true)
        ) {
            return $this->isAdminLoginAttempt($request);
        }

        return false;
    }

    private function isAdminLoginAttempt(ServerRequestInterface $request): bool
    {
        $body = $request->getParsedBody();
        if (! is_array($body)) {
            return false;
        }

        $identification = trim((string) Arr::get($body, 'identification', ''));
        if ($identification === '') {
            return false;
        }

        $user = $this->users->findByIdentification($identification);

        return $user !== null && $user->isAdmin();
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
