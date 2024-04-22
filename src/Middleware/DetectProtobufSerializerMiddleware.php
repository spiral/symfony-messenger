<?php

declare(strict_types=1);

namespace Spiral\Messenger\Middleware;

use Google\Protobuf\Internal\Message;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class DetectProtobufSerializerMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->getMessage() instanceof Message) {
            $envelope = $envelope->with(new \Symfony\Component\Messenger\Stamp\SerializerStamp([
                'serializer' => 'protobuf',
            ]));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
