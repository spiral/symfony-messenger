<?php

declare(strict_types=1);

namespace Spiral\Messenger\Pipeline\Provider;

use Spiral\Messenger\Pipeline\PipelineInterface;

final readonly class PipelineConfig
{
    /**
     * @param array<non-empty-string> $aliases
     */
    public function __construct(
        public PipelineInterface $pipeline,
        public array $aliases,
    ) {}
}
