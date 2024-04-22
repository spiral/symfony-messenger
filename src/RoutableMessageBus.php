<?php

declare(strict_types=1);

namespace Spiral\Messenger;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

final class RoutableMessageBus extends \Symfony\Component\Messenger\RoutableMessageBus
{
    private \IteratorAggregate $middlewareAggregate;

    public function __construct(
        iterable $middlewareHandlers,
        ContainerInterface $busLocator,
        ?MessageBusInterface $fallbackBus = null,
    ) {
        parent::__construct($busLocator, $fallbackBus);

        if ($middlewareHandlers instanceof \IteratorAggregate) {
            $this->middlewareAggregate = $middlewareHandlers;
        } elseif (\is_array($middlewareHandlers)) {
            $this->middlewareAggregate = new \ArrayObject($middlewareHandlers);
        } else {
            // $this->middlewareAggregate should be an instance of IteratorAggregate.
            // When $middlewareHandlers is an Iterator, we wrap it to ensure it is lazy-loaded and can be rewound.
            $this->middlewareAggregate = new class($middlewareHandlers) implements \IteratorAggregate {
                private \Traversable $middlewareHandlers;
                private \ArrayObject $cachedIterator;

                public function __construct(\Traversable $middlewareHandlers)
                {
                    $this->middlewareHandlers = $middlewareHandlers;
                }

                public function getIterator(): \Traversable
                {
                    return $this->cachedIterator ??= new \ArrayObject(
                        iterator_to_array($this->middlewareHandlers, false),
                    );
                }
            };
        }
    }

    public function dispatch(object $envelope, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($envelope, $stamps);
        $middlewareIterator = $this->middlewareAggregate->getIterator();

        while ($middlewareIterator instanceof \IteratorAggregate) {
            $middlewareIterator = $middlewareIterator->getIterator();
        }
        $middlewareIterator->rewind();

        if (!$middlewareIterator->valid()) {
            return parent::dispatch($envelope, $stamps);
        }

        $stack = new StackMiddleware($middlewareIterator);

        $envelope = $middlewareIterator->current()->handle($envelope, $stack);

        return parent::dispatch($envelope, $stamps);
    }
}
