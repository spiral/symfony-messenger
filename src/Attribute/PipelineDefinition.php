<?php

declare(strict_types=1);

namespace Spiral\Messenger\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class PipelineDefinition
{
    public readonly array $aliases;

    public function __construct(
        string ...$alias,
    ) {
        foreach ($alias as $name) {
            \assert($name !== '', 'Pipeline alias must not be empty.');
        }

        $this->aliases = $alias;
    }
}
