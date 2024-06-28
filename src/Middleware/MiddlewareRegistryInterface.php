<?php

declare(strict_types=1);

namespace Spiral\Messenger\Middleware;

use Spiral\Core\Container\Autowire;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

interface MiddlewareRegistryInterface extends \IteratorAggregate
{
    public const DEFAULT_PRIORITY = 10;
    public const HIGH_PRIORITY = 100;
    public const LOW_PRIORITY = 1;

    /**
     * @param MiddlewareInterface|Autowire<MiddlewareInterface>|class-string<MiddlewareInterface> $middleware
     */
    public function addMiddleware(
        MiddlewareInterface|Autowire|string $middleware,
        int $priority = self::DEFAULT_PRIORITY,
    ): void;
}
