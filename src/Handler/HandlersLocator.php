<?php

declare(strict_types=1);

namespace Spiral\Messenger\Handler;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Handler\InterceptorPipeline;
use Spiral\Interceptors\Handler\ReflectionHandler;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\Messenger\Stamp\AllowMultipleHandlers;
use Spiral\Messenger\Stamp\TargetHandler;
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
    public function __construct(
        #[Proxy] private readonly ContainerInterface $container,
        private readonly HandlersRegistryInterface $handlers,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {}

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
                    if (\in_array($name, $seen, true)) {
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
        $target = Target::fromPair($handler->class, $handler->method);
        $envelope = $envelope->with(new TargetHandler($target));

        return new HandlerDescriptor(
            handler: fn(mixed ...$arguments): mixed => $this
                ->prepareInterceptorPipeline()
                ->handle(new CallContext(
                    $target,
                    $arguments,
                    $envelope->all(),
                )),
            options: $handler->options,
        );
    }

    private function prepareInterceptorPipeline(): HandlerInterface {

        $container = $this->container->get(ContainerInterface::class);
        $h = new ReflectionHandler($container);
        $pipeline = new InterceptorPipeline($this->dispatcher);

        /** @var InterceptorInterface[] $interceptors */
        $interceptors = [];
        // todo resolve interceptors
        // foreach ([] as $interceptor) {
        //    $interceptors[] = $container->get($interceptor);
        // }
        $pipeline = $pipeline->withInterceptors(...$interceptors);

        return $pipeline->withHandler($h);
    }
}
