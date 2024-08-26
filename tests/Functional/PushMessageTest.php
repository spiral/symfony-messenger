<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\Functional;

use Spiral\Messenger\Stamp\PipelineStamp;
use Spiral\Messenger\Tests\Functional\App\Message\MessageWithPipeline;
use Spiral\Messenger\Tests\Functional\App\Message\MessageWithSerializer;
use Spiral\RoadRunner\Jobs\Task\PreparedTask;
use Symfony\Component\Messenger\Stamp\SerializerStamp;

final class PushMessageTest extends TestCase
{
    public function testSerializerShouldBeDetected(): void
    {
        $message = new MessageWithSerializer();

        $this->mockJobsService()
            ->shouldConnect('default')
            ->shouldBeDispatched(
                new PreparedTask(
                    name: $message::class,
                    payload: 'serialized',
                    headers: [
                        'type' => [$message::class],
                        'X-Message-Stamp-Symfony\Component\Messenger\Stamp\SerializerStamp' => ['[{"context":{"serializer":"protobuf"}}]'],
                        'Content-Type' => ['application/protobuf'],
                    ],
                ),
            );

        $this->mockSerializer()
            ->shouldSerialize($message, 'protobuf');

        $envelop = $this->getBus()->dispatch($message);
        $this->assertSame(
            ['serializer' => 'protobuf',],
            $envelop->last(SerializerStamp::class)->getContext(),
        );
    }

    public function testPipelineShouldBeDetected(): void
    {
        $message = new MessageWithPipeline();

        $this->mockJobsService()
            ->shouldConnect('some-pipeline')
            ->shouldBeDispatched(
                new PreparedTask(
                    name: $message::class,
                    payload: 'serialized',
                    headers: [
                        'type' => [$message::class],
                        'X-Message-Stamp-Symfony\Component\Messenger\Stamp\SerializerStamp' => ['[{"context":[]}]'],
                        'X-Message-Stamp-Spiral\Messenger\Stamp\PipelineStamp' => ['[{"pipeline":"some-pipeline"}]'],
                        'Content-Type' => ['application/json'],
                    ],
                ),
            );

        $this->mockSerializer()
            ->shouldSerialize($message, 'json');

        $envelop = $this->getBus()->dispatch($message);
        $this->assertSame(
            'some-pipeline',
            $envelop->last(PipelineStamp::class)->getPipeline(),
        );
    }
}
