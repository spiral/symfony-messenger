<?php

declare(strict_types=1);

namespace Spiral\Messenger\Pipeline;

use RoadRunner\Lock\LockInterface;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Container;
use Spiral\Messenger\Attribute\PipelineDefinition;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\Tokenizer\Attribute\TargetClass;
use Spiral\Tokenizer\TokenizationListenerInterface;

#[TargetClass(PipelineInterface::class)]
#[Singleton]
final class RoadRunnerPipelineRegistry implements PipelineRegistryInterface, TokenizationListenerInterface,
                                                  PipelineAliasesProviderInterface
{
    private const LOCK_RESOURCE = 'rr-jobs-registry';

    /**
     * Pipeline names that are already used and should not be used again.
     * @var non-empty-string[]
     */
    private array $usedPipelines = [];

    /** @var array<non-empty-string, array{0: PipelineInterface, 1: non-empty-string|null}> */
    private array $pipelines = [];

    /** @var array<non-empty-string, non-empty-string> */
    private array $pipelineAliases = [];

    public function __construct(
        private readonly Container $container,
        private readonly JobsInterface $service,
        private readonly LockInterface $lock,
    ) {
    }

    public function register(CreateInfoInterface $info, array $aliases = [], bool $consume = true): void
    {
        $name = $info->getName();
        foreach ($aliases as $alias) {
            $this->pipelineAliases[$alias] = $name;
        }

        $status = $this->createPipeline($info);

        if (!$status) {
            return;
        }

        $this->registerPipeline($name);

        if ($consume) {
            $this->service->resume($name);
        } else {
            $this->service->pause($name);
        }
    }

    private function createPipeline(CreateInfoInterface $info): bool
    {
        $lockId = $this->lock->lock(resource: self::LOCK_RESOURCE, ttl: 1, waitTTL: 1);

        if ($this->isExists($info->getName())) {
            $this->lock->release(resource: self::LOCK_RESOURCE, id: $lockId);
            return false;
        }

        $this->service->create($info);

        $this->lock->release(resource: self::LOCK_RESOURCE, id: $lockId);

        return true;
    }


    private function registerPipeline(string $name): void
    {
        if (\in_array($name, $this->usedPipelines, true)) {
            throw new \InvalidArgumentException("Pipeline with name $name already registered");
        }

        $this->usedPipelines[] = $name;
    }

    private function isExists(string $name): bool
    {
        $existPipelines = \array_keys(\iterator_to_array($this->service->getIterator()));

        return \in_array($name, $existPipelines, true);
    }

    public function listen(\ReflectionClass $class): void
    {
        /** @var PipelineInterface $pipeline */
        $pipeline = $this->container->get($class->getName());

        $aliases = [];
        $attrs = $class->getAttributes(PipelineDefinition::class);
        if ($attrs !== []) {
            /** @var PipelineDefinition $definition */
            $definition = $attrs[0]->newInstance();
            $aliases = $definition->aliases;
        }

        $this->pipelines[] = [$pipeline, $aliases];
    }

    public function finalize(): void
    {
        foreach ($this->pipelines as $pipeline) {
            [$pipeline, $aliases] = $pipeline;
            if (!$pipeline->shouldBeUsed()) {
                continue;
            }

            $this->register(
                info: $pipeline->info(),
                aliases: $aliases,
                consume: $pipeline->shouldConsume(),
            );
        }

        $this->pipelines = [];
    }

    public function getAliases(): array
    {
        return $this->pipelineAliases;
    }
}
