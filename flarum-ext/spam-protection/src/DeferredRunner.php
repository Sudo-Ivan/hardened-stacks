<?php

namespace HardenedStacks\SpamProtection;

use Psr\Log\LoggerInterface;
use Throwable;

final class DeferredRunner
{
    /**
     * @param callable():void $callback
     */
    public static function afterResponse(callable $callback): void
    {
        register_shutdown_function(static function () use ($callback): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }

            try {
                $callback();
            } catch (Throwable $e) {
                try {
                    /** @var LoggerInterface $logger */
                    $logger = resolve(LoggerInterface::class);
                    $logger->error('hardened-stacks-spam-protection: deferred job failed', [
                        'error' => $e->getMessage(),
                    ]);
                } catch (Throwable) {
                    error_log('hardened-stacks-spam-protection: deferred job failed: '.$e->getMessage());
                }
            }
        });
    }
}
