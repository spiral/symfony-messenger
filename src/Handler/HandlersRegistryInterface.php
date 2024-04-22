<?php

declare(strict_types=1);

namespace Spiral\Messenger\Handler;

interface HandlersRegistryInterface
{
    /**
     * @param class-string $message
     */
    public function registerHandler(string $message, Handler $handler): void;
}
