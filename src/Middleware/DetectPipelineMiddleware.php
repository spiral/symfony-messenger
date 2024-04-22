<?php

declare(strict_types=1);

namespace Spiral\Messenger\Middleware;

use Spiral\Messenger\Attribute\Pipeline;
use Spiral\Messenger\Stamp\PipelineStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class DetectPipelineMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Detect pipeline from message attribute only if not already set
        // It can't be overwritten by the message attribute
        if ($envelope->last(Pipeline::class) === null) {
            $refl = new \ReflectionClass($envelope->getMessage());
            $attr = $refl->getAttributes(Pipeline::class);

            if (\count($attr) > 0) {
                $envelope = $envelope->with(new PipelineStamp($attr[0]->newInstance()->pipeline));
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
