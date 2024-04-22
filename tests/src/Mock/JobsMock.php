<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\Mock;

use Mockery\MockInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;

final class JobsMock
{
    public function __construct(
        private readonly MockInterface $jobs,
    ) {
    }

    public function shouldConnect(string $pipeline): PipelineMock
    {
        $mock = \Mockery::mock(QueueInterface::class);

        $this->jobs->shouldReceive('connect')
            ->with($pipeline)
            ->andReturn($mock);

        return new PipelineMock($mock, $pipeline);
    }
}
