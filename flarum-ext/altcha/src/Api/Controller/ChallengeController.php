<?php

namespace HardenedStacks\Altcha\Api\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use HardenedStacks\Altcha\Service\AltchaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ChallengeController implements RequestHandlerInterface
{
    public function __construct(
        private AltchaService $altcha
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (! $this->altcha->isEnabled()) {
            return new JsonResponse(['error' => 'ALTCHA is not enabled'], 503);
        }

        return new JsonResponse($this->altcha->createChallenge());
    }
}
