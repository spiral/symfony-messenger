<?php

declare(strict_types=1);

namespace Spiral\Messenger\Handler;

use Spiral\Core\Attribute\Singleton;
use Spiral\Core\FactoryInterface;
use Spiral\Messenger\Attribute\HandlerMethod;
use Spiral\Messenger\Exception\InvalidHandlerException;
use Spiral\Messenger\Stamp\AllowMultipleHandlers;
use Spiral\Tokenizer\Attribute\TargetAttribute;
use Spiral\Tokenizer\TokenizationListenerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[TargetAttribute(HandlerMethod::class)]
#[Singleton]
final class HandlersLocator implements HandlersLocatorInterface, HandlersRegistryInterface,
                                       TokenizationListenerInterface
{
    /** @var array<class-string, array<int, Handler[]>> */
    private array $handlers = [];

    public function __construct(
        private readonly FactoryInterface $factory,
    ) {
    }

    public function getHandlers(Envelope $envelope): iterable
    {
        $seen = [];

        $handlerTypes = $this->handlers;

        $isMultipleHandlersAllowed = $envelope->last(AllowMultipleHandlers::class) !== null;

        foreach (self::listTypes($envelope) as $type) {
            $handlerTypes[$type] = $handlerTypes[$type] ?? [];
            \krsort($handlerTypes[$type]);

            foreach ($handlerTypes[$type] as $priority => $handlers) {
                foreach ($handlers as $handler) {
                    $handlerDescriptor = $this->buildHandlerDescriptor($handler);

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

    private function buildHandlerDescriptor(Handler $handler): HandlerDescriptor
    {
        return new HandlerDescriptor(
            handler: [
                $this->factory->make($handler->class),
                $handler->method,
            ],
            options: $handler->options,
        );
    }

    public function registerHandler(string $message, Handler $handler): void
    {
        $this->handlers[$message][$handler->priority][] = $handler;
    }

    /**
     * @throws InvalidHandlerException
     */
    public function listen(\ReflectionClass $class): void
    {
        foreach ($class->getMethods() as $method) {
            $attrs = $method->getAttributes(HandlerMethod::class);
            if (\count($attrs) === 0) {
                continue;
            }

            $attr = $attrs[0]->newInstance();

            foreach ($this->processHandler($method, $attr) as $message => $handler) {
                $this->handlers[$message][$handler->priority][] = $handler;
            }
        }
    }

    public function finalize(): void
    {
        // TODO: Implement finalize() method.
    }

    /**
     * @return iterable<class-string, Handler>
     * @throws InvalidHandlerException
     */
    private function processHandler(
        \ReflectionMethod $method,
        HandlerMethod $attr,
    ): iterable {
        $this->assertMethodIsPublic($method);

        foreach ($this->getMethodParameters($method) as $parameter) {
            if ($parameter->isBuiltin() || !\class_exists($parameter->getName())) {
                continue;
            }

            yield $parameter->getName() => new Handler(
                class: $method->getDeclaringClass()->getName(),
                method: $method->getName(),
                priority: $attr->priority,
                options: $attr->options,
            );
        }
    }

    /**
     * @throws InvalidHandlerException
     */
    private function assertMethodIsPublic(\ReflectionMethod $method): void
    {
        if (!$method->isPublic()) {
            throw new InvalidHandlerException(
                \sprintf(
                    'Handler method %s:%s should be public.',
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                ),
            );
        }
    }

    /**
     * @return \Traversable<int, \ReflectionNamedType>
     */
    private function getMethodParameters(\ReflectionMethod $method): \Traversable
    {
        foreach ($method->getParameters() as $parameter) {
            yield from $this->getReturnType($parameter->getType());
        }
    }

    private function getReturnType(\ReflectionType $type): \Traversable
    {
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                yield from $this->getReturnType($t);
            }
        } elseif ($type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $t) {
                yield from $this->getReturnType($t);
            }
        } else {
            yield $type;
        }
    }
}
