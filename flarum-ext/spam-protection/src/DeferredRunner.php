<?php

namespace HardenedStacks\SpamProtection;

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
            } catch (Throwable) {
            }
        });
    }
}
