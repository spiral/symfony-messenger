<?php

declare(strict_types=1);

namespace Spiral\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class PipelineStamp implements StampInterface
{
    public function __construct(
        public readonly string $pipeline,
    ) {
    }

    public function getPipeline(): string
    {
        return $this->pipeline;
    }
}
