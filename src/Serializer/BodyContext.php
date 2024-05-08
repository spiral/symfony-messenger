<?php

declare(strict_types=1);

namespace Spiral\Messenger\Serializer;

final readonly class BodyContext
{
    public const MESSENGER_SERIALIZATION_CONTEXT = 'messenger_serialization';

    public array $context;

    public function __construct(
        public string $format = 'json',
        array $context = [],
    ) {
        $this->context = $context + [self::MESSENGER_SERIALIZATION_CONTEXT => true];
    }
}
