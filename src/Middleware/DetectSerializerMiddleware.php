<?php

declare(strict_types=1);

namespace Spiral\Messenger\Middleware;

use Spiral\Messenger\Attribute\Serializer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\SerializerStamp;

final class DetectSerializerMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $context = $envelope->last(SerializerStamp::class)?->getContext() ?? [];

        // Try to detect serializer from message attribute if not already set
        // It can't be overwritten by the message attribute
        if (empty($context['serializer'])) {
            $refl = new \ReflectionClass($envelope->getMessage());
            $attr = $refl->getAttributes(Serializer::class);

            if (\count($attr) > 0) {
                $context['serializer'] = $attr[0]->newInstance()->serializer;
            }
        }

        return $stack->next()->handle($envelope->with(new SerializerStamp($context)), $stack);
    }
}
