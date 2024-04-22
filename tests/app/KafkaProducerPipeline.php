<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\App;

use Spiral\Boot\Environment\AppEnvironment;
use Spiral\Messenger\Attribute\PipelineDefinition;
use Spiral\Messenger\Pipeline\PipelineInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;

#[PipelineDefinition(name: 'my-queue')]
final class KafkaProducerPipeline implements PipelineInterface
{
    public function __construct(
        private readonly AppEnvironment $env,
    ) {
    }

    public function info(): CreateInfoInterface
    {
        return new \Spiral\RoadRunner\Jobs\Queue\KafkaCreateInfo(
            name: 'kafka',
            autoCreateTopicsEnable: true,
            producerOptions: new \Spiral\RoadRunner\Jobs\Queue\Kafka\ProducerOptions(),
            consumerOptions: new \Spiral\RoadRunner\Jobs\Queue\Kafka\ConsumerOptions(
                topics: ['scanners'],
            ),
            groupOptions: new \Spiral\RoadRunner\Jobs\Queue\Kafka\ConsumerGroupOptions(
                groupId: 'scanner-service',
            ),
        );
    }

    public function shouldConsume(): bool
    {
        return $this->env->isProduction();
    }

    public function shouldBeUsed(): bool
    {
        return $this->env->isProduction();
    }
}
