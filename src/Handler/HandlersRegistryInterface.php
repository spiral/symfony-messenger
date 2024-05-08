<?php

declare(strict_types=1);

namespace Spiral\Messenger\Handler;

interface HandlersRegistryInterface
{
    /**
     * @return array<class-string, array<int, HandlerConfig[]>>
     */
    public function getHandlers(): array;

    /**
     * @param class-string $message
     */
    public function registerHandler(string $message, HandlerConfig $handler): void;
}
