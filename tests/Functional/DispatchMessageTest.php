<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\Functional;

use Ramsey\Uuid\Uuid;
use Spiral\Messenger\Dispatcher;
use Spiral\Messenger\Exception\StopWorkerException;
use Spiral\Messenger\Tests\Functional\App\Message\Message;
use Spiral\RoadRunner\Payload;

final class DispatchMessageTest extends TestCase
{
    public function testMessageShouldBeHandled(): void
    {
        $worker = $this->mockWorker();
        $this->mockConsumer($worker)->shouldReceiveTask(
            id: Uuid::NIL,
            pipeline: 'default',
            job: Message::class,
            queue: 'queue',
            payload: 'serialized message',
            headers: [
                'type' => [Message::class],
                'X-Message-Stamp-Symfony\Component\Messenger\Stamp\SerializerStamp' => ['[{"context":{"serializer":"protobuf"}}]'],
                'Content-Type' => ['application/protobuf'],
            ],
        );

        $worker->shouldReceive('respond')->once()->with(\Mockery::on(static function (Payload $payload) {
            self::assertSame('{"type":0,"data":[]}', $payload->body);

            return true;
        }))->andThrow(new StopWorkerException('Failed to respond'));

        $this->mockSerializer()
            ->shouldDeserialize('serialized message', 'protobuf', $message = new Message());

        $dispatcher = $this->getContainer()->get(Dispatcher::class);

        $this->assertTrue($dispatcher->canServe());

        $dispatcher->serve();

        $this->assertSame($message, $message->envelope->getMessage());
    }
}
