<?php

declare(strict_types=1);

namespace Spiral\Messenger\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Serializer
{
    public function __construct(
        public readonly string $serializer,
    ) {
    }
}
