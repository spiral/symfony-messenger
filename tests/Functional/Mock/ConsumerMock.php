<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\Functional\Mock;

use Mockery\MockInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Queue\Driver;
use Spiral\RoadRunner\Jobs\Task\ReceivedTask;
use Spiral\RoadRunner\WorkerInterface;

final class ConsumerMock
{
    public function __construct(
        private readonly MockInterface&ConsumerInterface $consumer,
        private readonly MockInterface&WorkerInterface $worker,
    ) {
    }

    public function shouldReceiveTask(
        string $id,
        string $pipeline,
        string $job,
        string $queue,
        string $payload,
        array $headers = [],
        Driver $driver = Driver::Unknown,
    ): void {
        $task = new ReceivedTask(
            worker: $this->worker,
            id: $id,
            driver: $driver,
            pipeline: $pipeline,
            job: $job,
            queue: $queue,
            payload: $payload,
            headers: $headers,
        );

        $this->consumer
            ->shouldReceive('waitTask')
            ->once()
            ->andReturn($task);
    }
}
