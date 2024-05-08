<?php

declare(strict_types=1);

namespace Spiral\Messenger\Sender;

use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InterceptorPipeline;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Handler\ReflectionHandler;
use Spiral\Interceptors\HandlerInterface;
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
    private readonly HandlerInterface $interceptorPipeline;

    public function __construct(
        private readonly SendersLocatorInterface $locator,
        ?EventDispatcherInterface $dispatcher = null,
        ReflectionHandler $reflectionHandler,
    ) {
        $this->interceptorPipeline = (new InterceptorPipeline($dispatcher))
            ->withHandler($reflectionHandler);

        // todo add interceptors list
        // $this->interceptorPipeline->addInterceptor();
    }

    public function getSenders(Envelope $envelope): iterable
    {
        foreach ($this->locator->getSenders($envelope) as $alias => $sender) {
            \assert($sender instanceof SenderInterface);

            $target = Target::fromPair($sender, 'send');
            $wrapper = new class($this->interceptorPipeline, $target) implements SenderInterface {
                public function __construct(
                    private readonly HandlerInterface $handler,
                    private readonly Target $target,
                ) {
                }

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
}
