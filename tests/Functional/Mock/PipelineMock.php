<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\Functional\Mock;

use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunner\Jobs\Task\PreparedTaskInterface;
use Spiral\RoadRunner\Jobs\Task\QueuedTask;

final class PipelineMock
{
    public function __construct(
        private readonly MockInterface|QueueInterface $queue,
        private readonly string $pipeline,
    ) {
    }

    public function shouldBeDispatched(?PreparedTaskInterface $task = null, ?string $id = null): void
    {
        $this->queue->shouldReceive('dispatch')
            ->withArgs(function (PreparedTaskInterface $t) use ($task): bool {
                if ($task === null) {
                    return true;
                }

                TestCase::assertSame($t->getName(), $task->getName());
                TestCase::assertSame($t->getPayload(), $task->getPayload());
                TestCase::assertSame($t->getHeaders(), $task->getHeaders());

                return true;
            })
            ->andReturn(
                new QueuedTask(
                    id: $id ?? Uuid::NIL,
                    pipeline: $this->pipeline,
                    name: $task?->getName() ?? '',
                    payload: $task?->getPayload() ?? '',
                    headers: $task?->getHeaders() ?? [],
                ),
            );
    }
}
