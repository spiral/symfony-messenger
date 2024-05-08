<?php

declare(strict_types=1);

namespace Spiral\Messenger\Handler;

use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InterceptorPipeline;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Handler\ReflectionHandler;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Messenger\Stamp\AllowMultipleHandlers;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Symfony Messenger uses a locator to find handlers for a given message.
 * Here we use the HandlersRegistry to get the handlers for a given message.
 */
#[Singleton]
final class HandlersLocator implements HandlersLocatorInterface
{
    private readonly HandlerInterface $interceptorPipeline;

    public function __construct(
        private readonly HandlersRegistryInterface $handlers,
        ?EventDispatcherInterface $dispatcher = null,
        ReflectionHandler $reflectionHandler,
    ) {
        $this->interceptorPipeline = (new InterceptorPipeline($dispatcher))
            ->withHandler($reflectionHandler);

        // todo add interceptors list
        // $this->interceptorPipeline->addInterceptor();
    }

    public function getHandlers(Envelope $envelope): iterable
    {
        $seen = [];

        $handlerTypes = $this->handlers->getHandlers();

        $isMultipleHandlersAllowed = $envelope->last(AllowMultipleHandlers::class) !== null;

        foreach (self::listTypes($envelope) as $type) {
            $handlerTypes[$type] = $handlerTypes[$type] ?? [];
            \krsort($handlerTypes[$type]);

            foreach ($handlerTypes[$type] as $priority => $handlers) {
                foreach ($handlers as $handler) {
                    $handlerDescriptor = $this->buildHandlerDescriptor($handler, $envelope);

                    if (!$this->shouldHandle($envelope, $handlerDescriptor)) {
                        continue;
                    }

                    $name = $handlerDescriptor->getName();
                    if (\in_array($name, $seen)) {
                        continue;
                    }

                    $seen[] = $name;

                    yield $handlerDescriptor;

                    // if multiple handlers are allowed, we continue to the next one
                    if (!$isMultipleHandlersAllowed) {
                        break;
                    }
                }
            }
        }
    }

    /** @internal */
    public static function listTypes(Envelope $envelope): array
    {
        $class = \get_class($envelope->getMessage());

        return [$class => $class]
            + \class_parents($class)
            + \class_implements($class)
            + ['*' => '*'];
    }

    private function shouldHandle(Envelope $envelope, HandlerDescriptor $handlerDescriptor): bool
    {
        if (null === $received = $envelope->last(ReceivedStamp::class)) {
            return true;
        }

        if (null === $expectedTransport = $handlerDescriptor->getOption('from_transport')) {
            return true;
        }

        /** @var ReceivedStamp $received */
        return $received->getTransportName() === $expectedTransport;
    }

    private function buildHandlerDescriptor(HandlerConfig $handler, Envelope $envelope): HandlerDescriptor
    {
        return new HandlerDescriptor(
            handler: function (mixed ...$arguments) use ($handler, $envelope): mixed {
                // Create call context to intercept job invocation
                $callContext = new CallContext(
                    Target::fromPair($handler->class, $handler->method),
                    $arguments,
                    $envelope->all(),
                );

                // Run interceptors pipeline
                return $this->interceptorPipeline->handle($callContext);
            },
            options: $handler->options,
        );
    }
}
