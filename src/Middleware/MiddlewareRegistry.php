<?php

declare(strict_types=1);

namespace Spiral\Messenger\Middleware;

use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Container;
use Spiral\Core\Container\Autowire;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Traversable;

#[Singleton]
final class MiddlewareRegistry implements MiddlewareRegistryInterface
{
    private array $middlewares = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function getIterator(): Traversable
    {
        \krsort($this->middlewares);

        foreach ($this->middlewares as $priority => $middlewares) {
            yield from $middlewares;
        }
    }

    public function addMiddleware(
        MiddlewareInterface|Autowire|string $middleware,
        int $priority = self::DEFAULT_PRIORITY,
    ): void {
        if (\is_string($middleware) || $middleware instanceof Container\Autowire) {
            $middleware = $this->container->get($middleware);
            \assert(
                $middleware instanceof MiddlewareInterface,
                'Middleware must be an instance of ' . MiddlewareInterface::class,
            );
        }

        $this->middlewares[$priority][] = $middleware;
    }
}
