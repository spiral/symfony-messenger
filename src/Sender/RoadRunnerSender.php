<?php

declare(strict_types=1);

namespace Spiral\Messenger\Sender;

use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Messenger\Exception\PipelineRequiredException;
use Spiral\Messenger\Stamp\HeadersStamp;
use Spiral\Messenger\Stamp\OptionsStamp;
use Spiral\Messenger\Stamp\PipelineStamp;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\Task\PreparedTask;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class RoadRunnerSender implements SenderInterface
{
    public function __construct(
        private readonly JobsInterface $jobs,
        private readonly SerializerInterface $serializer,
        private readonly ExceptionReporterInterface $reporter,
        private readonly array $aliases = [],
        private readonly ?string $defaultPipeline = null,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        // Get pipeline from envelope or use default pipeline
        $pipeline = $envelope->last(PipelineStamp::class)?->getPipeline() ?? $this->defaultPipeline;

        // If pipeline is an alias, map it to the real pipeline name
        if (isset($this->aliases[$pipeline])) {
            $pipeline = $this->aliases[$pipeline];
        }

        if ($pipeline === null) {
            throw new PipelineRequiredException(
                \sprintf('Pipeline required for message %s', $envelope->getMessage()::class),
            );
        }

        $payload = $this->serializer->encode($envelope);

        $options = $envelope->last(OptionsStamp::class)?->getOptions() ?? new Options();

        $delay = $envelope->last(DelayStamp::class)?->getDelay();
        // If delay is set, add it to options
        if ($delay > 0) {
            $options = $options->withDelay(
            // Delay in seconds
                (int)($delay / 1000),
            );
        }

        if (!isset($payload['headers'])) {
            $payload['headers'] = [];
        }

        // TODO: use mapper for headers
        foreach ($envelope->all(HeadersStamp::class) as $headers) {
            $payload['headers'] = [...$payload['headers'], ...$headers->getHeaders()];
        }

        foreach ($payload['headers'] as $key => $value) {
            $options = $options->withHeader($key, $value);
        }

        try {
            $task = new PreparedTask(
                name: $envelope->getMessage()::class,
                payload: $payload['body'],
                options: $options,
            );

            $sentTask = $this->jobs->connect($pipeline)->dispatch($task);

            $envelope = $envelope->with(
                new TransportMessageIdStamp($sentTask->getId()),
                new PipelineStamp($pipeline),
            );
        } catch (JobsException $e) {
            $this->reporter->report($e);

            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope;
    }
}
