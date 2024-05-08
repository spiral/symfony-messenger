<?php

declare(strict_types=1);

namespace Spiral\Messenger\Attribute;

use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Apply to the Message to specify which Sender should be used for sending.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TargetSender
{
    /**
     * @param class-string<SenderInterface>|non-empty-string $name
     */
    public function __construct(
        public readonly string $name,
    ) {
    }
}
