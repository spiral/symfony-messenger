<?php

declare(strict_types=1);

namespace Spiral\Messenger\Stamp;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class RetryHandlerStamp implements StampInterface
{
    public function __construct(
        private readonly \Closure $handler,
    ) {
    }

    public function retry(Envelope $envelope, ?\Throwable $e = null): void
    {
        ($this->handler)($envelope, $e);
    }
}
