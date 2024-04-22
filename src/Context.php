<?php

declare(strict_types=1);

namespace Spiral\Messenger;

use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Messenger\Envelope;

final class Context implements ContextInterface
{
    public function __construct(
        private readonly Envelope $envelope,
        private readonly ReceivedTaskInterface $task,
    ) {
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getTask(): ReceivedTaskInterface
    {
        return $this->task;
    }
}
