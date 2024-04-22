<?php

declare(strict_types=1);

namespace Spiral\Messenger\Pipeline;

use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;

interface PipelineInterface
{
    public function info(): CreateInfoInterface;

    /**
     * Should pipeline consume messages from the queue.
     */
    public function shouldConsume(): bool;

    /**
     * Should pipeline be used in a current environment.
     */
    public function shouldBeUsed(): bool;
}
