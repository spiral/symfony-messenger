<?php

declare(strict_types=1);

namespace Spiral\Messenger;

use Psr\Container\ContainerInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Messenger\Dispatcher\TaskState;
use Spiral\Messenger\Exception\RetryException;
use Spiral\Messenger\Pipeline\PipelineProviderInterface;
use Spiral\Messenger\Pipeline\PipelineRegistryInterface;
use Spiral\Messenger\Pipeline\Provider\PipelineConfig;
use Spiral\Messenger\Serializer\StampSerializer;
use Spiral\Messenger\Stamp\RetryHandlerStamp;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\OptionsInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

#[DispatcherScope(scope: 'queue')]
final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly EnvironmentInterface $environment,
        private readonly SerializerInterface $serializer,
        private readonly ExceptionReporterInterface $reporter,
        private readonly StampSerializer $stampSerializer,
        private readonly PipelineProviderInterface $pipelines,
    ) {
    }

    public function canServe(): bool
    {
        return \PHP_SAPI === 'cli' && $this->environment->getMode() === Mode::MODE_JOBS;
    }

    public function serve(): void
    {
        /** @var ConsumerInterface $consumer */
        $consumer = $this->container->get(ConsumerInterface::class);
        $this->registerPipelines($this->container->get(PipelineRegistryInterface::class));
        /** @var ScopeInterface $scopeRunner */
        $scopeRunner = $this->container->get(ScopeInterface::class);
        /** @var FinalizerInterface $finalizer */
        $finalizer = $this->container->get(FinalizerInterface::class);

        while ($task = $consumer->waitTask()) {
            // TODO: use mapper for headers
            $headers = [];
            foreach ($task->getHeaders() as $key => $value) {
                $headers[$key] = $task->getHeaderLine($key);
            }

            $envelope = $this->serializer->decode([
                'body' => $task->getPayload(),
                'headers' => $headers,
            ])->with(
                new TransportMessageIdStamp($task->getId()),
            );

            try {
                $this->handleMessage($scopeRunner, $task, $envelope);
            } catch (\Throwable $e) {
                $this->reporter->report($e);
                $task->fail($e);
            }

            $finalizer->finalize(terminate: false);
        }
    }

    private function handleMessage(ScopeInterface $scopeRunner, ReceivedTaskInterface $task, Envelope $envelope): void
    {
        $state = new TaskState($this->stampSerializer, $task);
        $context = new Context($envelope, $task);
        $envelope = $scopeRunner->runScope(
            new Scope(
                name: 'task',
                bindings: [
                    ContextInterface::class => $context,
                ],
            ),
            static fn(Container $container): Envelope => $container
                ->get(MessageBusInterface::class)
                ->dispatch($envelope, [
                    new ConsumedByWorkerStamp(),
                    new AckStamp($state->ack(...)),
                    new RetryHandlerStamp($state->retry(...)),
                    new ReceivedStamp('roadrunner'),
                ]),
        );

        $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);
        if (!$state->isProcessed() && !$noAutoAckStamp) {
            $state->ack($envelope);
        }
    }

    public function retry(RetryException $e, ReceivedTaskInterface $task): void
    {
        $options = $e->getOptions();

        if (($options instanceof OptionsInterface) && ($delay = $options->getDelay()) !== null) {
            $task = $task->withDelay($delay);
        }

        $task->fail($e, true);
    }

    private function registerPipelines(PipelineRegistryInterface $registry): void
    {
        /** @var PipelineConfig $item */
        foreach ($this->pipelines->getIterator() as $item) {
            if (!$item->pipeline->shouldBeUsed()) {
                continue;
            }

            $registry->register(
                info: $item->pipeline->info(),
                aliases: $item->aliases,
                consume: $item->pipeline->shouldConsume(),
            );
        }
    }
}
