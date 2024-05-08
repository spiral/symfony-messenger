<?php

declare(strict_types=1);

namespace Spiral\Messenger\Sender;

use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

interface SenderMapRegistryInterface
{
    /**
     * Register sender for specific handler.
     *
     * @param non-empty-string $handler
     * @param class-string<SenderInterface>|non-empty-string $sender
     */
    public function register(string $handler, string $sender): void;
}
