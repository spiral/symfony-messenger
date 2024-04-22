<?php

declare(strict_types=1);

namespace Spiral\Messenger\Pipeline;

use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;

interface PipelineRegistryInterface
{
    public function register(CreateInfoInterface $info, array $aliases = [], bool $consume = true): void;
}
