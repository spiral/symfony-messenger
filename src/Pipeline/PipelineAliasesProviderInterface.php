<?php

declare(strict_types=1);

namespace Spiral\Messenger\Pipeline;

use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;

interface PipelineAliasesProviderInterface
{
    /**
     * Get pipeline aliases.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getAliases(): array;
}
