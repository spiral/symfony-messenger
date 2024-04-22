<?php

declare(strict_types=1);

namespace Spiral\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class RetryPolicyStamp implements StampInterface
{
    public function __construct(
        public readonly int $attempts = 0,
    ) {
    }
}
