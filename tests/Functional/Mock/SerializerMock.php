<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\Functional\Mock;

use Mockery\MockInterface;
use Spiral\Serializer\SerializerInterface;

final class SerializerMock
{
    public function __construct(
        private readonly MockInterface $serializer,
    ) {
    }

    public function shouldSerialize(object|string $message, string $format, ?string $serializedPayload = null): self
    {
        $serializer = \Mockery::mock(SerializerInterface::class);

        $this->serializer->shouldReceive('get')
            ->once()
            ->with($format)
            ->andReturn($serializer);

        $serializer->shouldReceive('serialize')
            ->once()
            ->withArgs(function (mixed $m) use ($message): bool {
                if (\is_string($message) && \is_object($m)) {
                    return $m::class === $message;
                }

                return $m === $message;
            })
            ->andReturn($serializedPayload ?? 'serialized');

        return $this;
    }

    public function shouldDeserialize(string $payload, string $format, object $message): self
    {
        $serializer = \Mockery::mock(SerializerInterface::class);

        $this->serializer->shouldReceive('get')
            ->once()
            ->with($format)
            ->andReturn($serializer);

        $serializer->shouldReceive('unserialize')
            ->once()
            ->with($payload, $message::class)
            ->andReturn($message);

        return $this;
    }
}
