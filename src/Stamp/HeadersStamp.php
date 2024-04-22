<?php

declare(strict_types=1);

namespace Spiral\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class HeadersStamp implements StampInterface
{
    /** @param array<non-empty-string, string|int> $headers */
    public function __construct(
        private readonly array $headers,
    ) {
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
