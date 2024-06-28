<?php

declare(strict_types=1);

namespace Spiral\Messenger\Pipeline;

use IteratorAggregate;
use Spiral\Messenger\Pipeline\Provider\PipelineConfig;
use Traversable;

/**
 * @extends  IteratorAggregate<PipelineInterface, array<non-empty-string>>
 */
interface PipelineProviderInterface extends IteratorAggregate
{
    /**
     * @return Traversable<int, PipelineConfig>
     */
    public function getIterator(): Traversable;
}
