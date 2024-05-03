<?php

declare(strict_types=1);

namespace Spiral\Messenger\Handler;

use Spiral\Core\Attribute\Singleton;
use Spiral\Messenger\Attribute\HandlerMethod;
use Spiral\Messenger\Exception\InvalidHandlerException;
use Spiral\Tokenizer\Attribute\TargetAttribute;
use Spiral\Tokenizer\TokenizationListenerInterface;

#[TargetAttribute(HandlerMethod::class)]
#[Singleton]
final class HandlersRegistry implements HandlersRegistryInterface, TokenizationListenerInterface
{
    /** @var array<class-string, array<int, HandlerConfig[]>> */
    private array $handlers = [];

    /**
     * @return array<class-string, array<int, HandlerConfig[]>>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    public function registerHandler(string $message, HandlerConfig $handler): void
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
     * @return iterable<class-string, HandlerConfig>
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

            yield $parameter->getName() => new HandlerConfig(
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
