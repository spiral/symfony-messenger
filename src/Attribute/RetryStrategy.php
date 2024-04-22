<?php

declare(strict_types=1);

namespace Spiral\Messenger\Attribute;

use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class RetryStrategy
{
    /**
     * @param int<0,max> $maxAttempts
     * @param positive-int $delay in seconds.
     */
    public function __construct(
        protected readonly int $maxAttempts = 3,
        protected readonly int $delay = 1,
        protected readonly float $multiplier = 1,
    ) {
    }

    public function getRetryStrategy(): RetryStrategyInterface
    {
        return new MultiplierRetryStrategy(
            maxRetries: $this->maxAttempts,
            delayMilliseconds: $this->delay * 1000,
            multiplier: $this->multiplier,
        );
    }
}
