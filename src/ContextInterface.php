<?php

declare(strict_types=1);

namespace Spiral\Messenger;

use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Messenger\Envelope;

interface ContextInterface
{
    public function getEnvelope(): Envelope;

    public function getTask(): ReceivedTaskInterface;
}
