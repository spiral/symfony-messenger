<?php

declare(strict_types=1);

namespace Spiral\Messenger\Pipeline;

use RoadRunner\Lock\LockInterface;
use Spiral\Core\Attribute\Singleton;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\Tokenizer\Attribute\TargetClass;

/**
 * Registers pipelines in the RoadRunner.
 *
 * @internal
 */
#[TargetClass(PipelineInterface::class)]
#[Singleton]
final class RoadRunnerPipelineRegistry implements PipelineRegistryInterface
{
    private const LOCK_RESOURCE = 'rr-jobs-registry';

    /**
     * Pipeline names that are already used and should not be used again.
     * @var non-empty-string[]
     */
    private array $usedPipelines = [];

    public function __construct(
        private readonly JobsInterface $service,
        private readonly LockInterface $lock,
    ) {
    }

    /**
     * @param list<non-empty-string> $aliases
     */
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
}
