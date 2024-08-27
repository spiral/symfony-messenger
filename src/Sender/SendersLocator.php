<?php

declare(strict_types=1);

namespace Spiral\Messenger\Sender;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Container\Autowire;
use Spiral\Core\FactoryInterface;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Handler\InterceptorPipeline;
use Spiral\Interceptors\Handler\ReflectionHandler;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\Messenger\Config\MessengerConfig;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * Symfony Messenger uses a locator to find senders for a given message.
 *
 * The class adds interceptor functionality through {@see SendersLocatorInterface} decoration.
 */
#[Singleton]
final class SendersLocator implements SendersLocatorInterface
{
    public function __construct(
        #[Proxy] private readonly ContainerInterface $container,
        private readonly SendersProviderInterface $provider,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {}

    public function getSenders(Envelope $envelope): iterable
    {
        /** @var ContainerInterface $container */
        $container = $this->container->get(ContainerInterface::class);
        $locator = new \Symfony\Component\Messenger\Transport\Sender\SendersLocator(
            sendersMap: $this->provider->getSenders(),
            sendersLocator: $container,
        );
        $interceptors = $this->prepareInterceptorPipeline($container);

        foreach ($locator->getSenders($envelope) as $alias => $sender) {
            \assert($sender instanceof SenderInterface);

            $target = Target::fromPair($sender, 'send');
            $wrapper = new class($interceptors, $target) implements SenderInterface {
                public function __construct(
                    private readonly HandlerInterface $handler,
                    private readonly Target $target,
                ) {}

                public function send(Envelope $envelope): Envelope
                {
                    $context = new CallContext($this->target, [$envelope]);
                    $result = $this->handler->handle($context);
                    \assert($result instanceof Envelope);
                    return $result;
                }
            };

            yield $alias => $wrapper;
        }
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
        foreach ($config->getOutboundInterceptors() as $interceptor) {
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
