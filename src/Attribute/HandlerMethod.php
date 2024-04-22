<?php

declare(strict_types=1);

namespace Spiral\Messenger\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class HandlerMethod
{
    public function __construct(
        public readonly int $priority = 0,
        public readonly array $options = [],
    ) {
    }
}
