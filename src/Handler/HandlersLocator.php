<?php

declare(strict_types=1);

namespace Spiral\Messenger\Handler;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Container\Autowire;
use Spiral\Core\FactoryInterface;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Handler\InterceptorPipeline;
use Spiral\Interceptors\Handler\ReflectionHandler;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\Messenger\Config\MessengerConfig;
use Spiral\Messenger\Context;
use Spiral\Messenger\ContextInterface;
use Spiral\Messenger\Stamp\AllowMultipleHandlers;
use Spiral\Messenger\Stamp\TargetHandler;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
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
            handler: function (mixed ...$arguments) use ($target, $envelope): mixed {
                /** @var ScopeInterface $scope */
                $scope = $this->container->get(ScopeInterface::class);
                /** @var ReceivedTaskInterface $task */
                $task = $this->container->get(ReceivedTaskInterface::class);

                return $scope->runScope(
                    new Scope(
                        name: 'queue-task',
                        bindings: [
                            ContextInterface::class => new Context($envelope, $task),
                        ],
                    ),
                    fn(ContainerInterface $container): mixed => $this
                        ->prepareInterceptorPipeline($container)
                        ->handle(new CallContext($target, $arguments, $envelope->all())),
                );
            },
            options: $handler->options,
        );
    }

    private function prepareInterceptorPipeline(ContainerInterface $container): HandlerInterface
    {
        /** @var FactoryInterface $factory */
        $factory = $this->container->get(FactoryInterface::class);
        /** @var MessengerConfig $config */
        $config = $container->get(MessengerConfig::class);

        $h = new ReflectionHandler($container);
        $pipeline = new InterceptorPipeline($this->dispatcher);

        /** @var InterceptorInterface[] $interceptors */
        $interceptors = [];
        foreach ($config->getInboundInterceptors() as $interceptor) {
           $interceptors[] = $this->autowire($interceptor, $container, $factory);
        }

        $pipeline = $pipeline->withInterceptors(...$interceptors);

        return $pipeline->withHandler($h);
    }

    /**
     * @template T of InterceptorInterface
     *
     * @param class-string<T>|Autowire<T>|T $id
     *
     * @return T
     *
     * @throws ContainerExceptionInterface
     */
    private function autowire(
        string|object $id,
        ContainerInterface $container,
        FactoryInterface $factory,
    ): InterceptorInterface {
        return match (true) {
            \is_string($id) => $container->get($id),
            $id instanceof Autowire => $id->resolve($factory),
            default => $id,
        };
    }
}
