<?php

namespace Spiral\Messenger\Tests\Functional;

use Mockery\MockInterface;
use Spiral\Core\Container;
use Spiral\Messenger\Tests\Functional\App\Kernel;
use Spiral\Messenger\Tests\Functional\App\Mock\ConsumerMock;
use Spiral\Messenger\Tests\Functional\App\Mock\JobsMock;
use Spiral\Messenger\Tests\Functional\App\Mock\SerializerMock;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\Serializer\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\Testing\TestableKernelInterface;
use Spiral\Testing\TestApp;
use Symfony\Component\Messenger\MessageBusInterface;

abstract class TestCase extends \Spiral\Testing\TestCase
{
    public function rootDirectory(): string
    {
        return __DIR__ . '/../';
    }

    public function createAppInstance(Container $container = new Container()): TestableKernelInterface
    {
        return Kernel::create(
            $this->defineDirectories($this->rootDirectory()),
            false
        );
    }

    public function getBus(): MessageBusInterface
    {
        return $this->getContainer()->get(MessageBusInterface::class);
    }

    public function mockJobsService(): JobsMock
    {
        return new JobsMock(
            $this->mockContainer(JobsInterface::class),
        );
    }

    public function mockSerializer(): SerializerMock
    {
        return new SerializerMock(
            $this->mockContainer(SerializerRegistryInterface::class),
        );
    }

    public function mockWorker(): MockInterface&WorkerInterface
    {
        return $this->mockContainer(WorkerInterface::class);
    }

    public function mockConsumer(?MockInterface $worker = null): ConsumerMock
    {
        \assert($worker instanceof WorkerInterface || $worker === null);

        return new ConsumerMock(
            consumer: $this->mockContainer(ConsumerInterface::class),
            worker: $worker ?? $this->mockWorker(),
        );
    }
}
