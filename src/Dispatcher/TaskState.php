<?php

declare(strict_types=1);

namespace Spiral\Messenger\Dispatcher;

use Spiral\Messenger\Serializer\StampSerializer;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class TaskState
{
    private const STATE_PENDING = 0;
    private const STATE_COMPLETED = 1;
    private const STATE_FAILED = 2;
    private const STATE_RETRIED = 3;

    private int $state;

    public function __construct(
        public readonly StampSerializer $serializer,
        public readonly ReceivedTaskInterface $task,
    ) {
    }

    /**
     * Handler that marks the task as completed or failed.
     *
     * Used in {@see \Symfony\Component\Messenger\Stamp\AckStamp}
     */
    public function ack(Envelope $envelope, ?\Throwable $e = null): void
    {
        if ($this->state !== self::STATE_PENDING) {
            return;
        }

        if ($e !== null) {
            $this->state = self::STATE_FAILED;
            $this->task->fail($e);
            return;
        }

        $this->state = self::STATE_COMPLETED;
        $this->task->complete();
    }

    /**
     * Handler that sends the task for retry.
     *
     * Used in {@see \Spiral\Messenger\Stamp\RetryHandlerStamp}
     */
    public function retry(Envelope $envelope, ?\Throwable $e = null): void
    {
        if ($this->state !== self::STATE_PENDING) {
            return;
        }

        $this->state = self::STATE_RETRIED;

        $task = $this->task;
        $headers = $this->serializer->encodeStamps($envelope);

        // Deal with delay
        $delay = $envelope->last(DelayStamp::class)?->getDelay();
        if ($delay >= 1000) {
            $task = $task->withDelay(\intdiv($delay, 1000));
        }

        // Set headers to task
        foreach ($headers as $key => $value) {
            if ($value === '') {
                continue;
            }

            $task = $task->withHeader($key, $value);
        }

        $task->fail($e, requeue: true);
    }

    public function isCompleted(): bool
    {
        return $this->state === self::STATE_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->state === self::STATE_FAILED;
    }

    public function isProcessed(): bool
    {
        return $this->state !== self::STATE_PENDING;
    }

    public function isRetried(): bool
    {
        return $this->state === self::STATE_RETRIED;
    }
}
