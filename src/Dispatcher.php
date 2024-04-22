<?php

declare(strict_types=1);

namespace Spiral\Messenger;

use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Messenger\Exception\RetryException;
use Spiral\Messenger\Exception\StopWorkerException;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
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

final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly FinalizerInterface $finalizer,
        private readonly EnvironmentInterface $environment,
        private readonly SerializerInterface $serializer,
        private readonly ExceptionReporterInterface $reporter,
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
                $this->handleMessage($task, $envelope);
            } catch (\Throwable $e) {
                $this->reporter->report($e);
                $task->fail($e);
            }

            $this->finalizer->finalize(terminate: false);
        }
    }

    private function handleMessage(ReceivedTaskInterface $task, Envelope $envelope): void
    {
        $acked = false;
        $ack = function (Envelope $envelope, ?\Throwable $e = null) use ($task, &$acked): void {
            $acked = true;

            if ($e !== null) {
                $task->fail($e);
                return;
            }

            $task->complete();
        };

        $context = new Context($envelope, $task);

        $envelope = $this->container->runScope(
            new Scope(
                name: 'jobs.queue',
                bindings: [
                    ContextInterface::class => $context,
                ],
            ),
            function (Container $container) use ($envelope, $ack): Envelope {
                return $container->get(MessageBusInterface::class)->dispatch($envelope, [
                    new ConsumedByWorkerStamp(),
                    new AckStamp($ack),
                    new ReceivedStamp('roadrunner'),
                ]);
            },
        );

        $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

        if (!$acked && !$noAutoAckStamp) {
            $ack($envelope);
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
}
