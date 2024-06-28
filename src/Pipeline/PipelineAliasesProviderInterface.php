<?php

declare(strict_types=1);

namespace Spiral\Messenger\Pipeline;

interface PipelineAliasesProviderInterface
{
    /**
     * Get pipeline aliases.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function getAliases(): array;
}
