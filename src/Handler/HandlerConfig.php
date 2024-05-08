<?php

declare(strict_types=1);

namespace Spiral\Messenger\Handler;

final class HandlerConfig
{
    public function __construct(
        public readonly string $class,
        public readonly string $method,
        public readonly int $priority = 0,
        public readonly ?string $bus = null,
        public readonly array $options = [],
    ) {
    }
}
