<?php

declare(strict_types=1);

namespace Spiral\Messenger\Pipeline;

use Psr\Container\ContainerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Messenger\Attribute\PipelineDefinition;
use Spiral\Messenger\Pipeline\Provider\PipelineConfig;
use Spiral\Tokenizer\Attribute\TargetClass;
use Spiral\Tokenizer\TokenizationListenerInterface;
use Traversable;

/**
 * @internal
 */
#[TargetClass(PipelineInterface::class)]
#[Singleton]
final class PipelinesProvider implements PipelineProviderInterface, TokenizationListenerInterface, PipelineAliasesProviderInterface
{
    /** @var list<\ReflectionClass<PipelineInterface>> */
    private array $configsBuffer = [];

    /** @var list<PipelineConfig> */
    private array $items = [];

    public function __construct(
        #[Proxy] private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @param \ReflectionClass<PipelineInterface> $class
     */
    public function listen(\ReflectionClass $class): void
    {
        if ($class->isInterface()) {
            return;
        }

        $this->configsBuffer[] = $class;
    }

    public function finalize(): void
    {
        // Do nothing
    }

    public function getIterator(): Traversable
    {
        $this->resolveBuffer();
        yield from $this->items;
    }

    public function getAliases(): array
    {
        $this->resolveBuffer();

        /** @var list<non-empty-string, non-empty-string> $result */
        $result = [];
        foreach ($this->items as $item) {
            foreach ($item->aliases as $alias) {
                $result[$alias] = $item->pipeline->info()->getName();
            }
        }

        return $result;
    }

    private function resolveBuffer(): void
    {
        /** @var \ReflectionClass<PipelineInterface> $class */
        while (null !== $class = \array_shift($this->configsBuffer)) {
            /** @var PipelineInterface $config */
            $config = $this->container->get($class->getName());
            $aliases = [];

            $attr = $class->getAttributes(PipelineDefinition::class)[0] ?? null;
            if ($attr !== null) {
                /** @var PipelineDefinition $definition */
                $definition = $attr->newInstance();
                $aliases = $definition->aliases;
            }

            $this->items[] = new PipelineConfig($config, $aliases);
        }
    }
}
