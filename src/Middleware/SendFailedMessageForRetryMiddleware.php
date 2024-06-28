<?php

declare(strict_types=1);

namespace Spiral\Messenger\Middleware;

use Spiral\Messenger\Attribute\RetryStrategy;
use Spiral\Messenger\Stamp\RetryHandlerStamp;
use Spiral\Messenger\Stamp\TargetHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

final class SendFailedMessageForRetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SendersLocatorInterface $sendersLocator,
        private readonly int $historySize = 10,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $e) {
            $strategy = null;

            $refl = $envelope->last(TargetHandler::class)?->target->getReflection();

            if ($e instanceof HandlerFailedException) {
                $exceptions = $e->getWrappedExceptions();
                $last = \end($exceptions);

                if ($refl !== null) {
                    // get last error
                    $strategy = $this->getRetryStrategy($last, $refl);
                } else {
                    // get last key of array
                    $lastKey = \array_key_last($exceptions);

                    if ($lastKey === null) {
                        throw $e;
                    }

                    try {
                        [$handler, $method] = \explode('@', $lastKey);
                        $refl = new \ReflectionMethod($handler, $method);
                    } catch (\Throwable) {
                        throw $e;
                    }

                    $strategy = $this->getRetryStrategy($exceptions[$lastKey], $refl);
                }
            }

            if ($strategy === null) {
                throw $e;
            }

            if (!$this->shouldRetry($e, $envelope, $strategy)) {
                throw $e;
            }

            $delay = $strategy->getWaitingTime($envelope, $e);
            $retryCount = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
            $retryHandler = $envelope->last(RetryHandlerStamp::class);

            // add the delay and retry stamp info
            $retryEnvelope = $this->withLimitedHistory(
                $envelope,
                new DelayStamp($delay),
                new RedeliveryStamp($retryCount + 1),
            )
                ->withoutAll(ReceivedStamp::class)
                ->withoutAll(ConsumedByWorkerStamp::class)
                ->withoutAll(RetryHandlerStamp::class)
                ->withoutAll(AckStamp::class);

            // Check there is a retry handler stamp
            if ($retryHandler !== null) {
                // Use contextual task pusher
                $retryHandler->retry($retryEnvelope, $e);
            } else {
                foreach ($this->sendersLocator->getSenders($retryEnvelope) as $sender) {
                    $sender->send($retryEnvelope);
                }
            }

            // TODO: rethrow the exception to be handled by the next middleware???
            return $retryEnvelope;
        }
    }

    private function shouldRetry(\Throwable $e, Envelope $envelope, ?RetryStrategyInterface $strategy = null): bool
    {
        if ($e instanceof RecoverableExceptionInterface) {
            return true;
        }

        // if one or more nested Exceptions is an instance of RecoverableExceptionInterface we should retry
        // if ALL nested Exceptions are an instance of UnrecoverableExceptionInterface we should not retry
        if ($e instanceof HandlerFailedException) {
            $shouldNotRetry = true;
            foreach ($e->getWrappedExceptions() as $nestedException) {
                if ($nestedException instanceof RecoverableExceptionInterface) {
                    return true;
                }

                if (!$nestedException instanceof UnrecoverableExceptionInterface) {
                    $shouldNotRetry = false;
                    break;
                }
            }
            if ($shouldNotRetry) {
                return false;
            }
        }

        if ($e instanceof UnrecoverableExceptionInterface) {
            return false;
        }

        if ($strategy === null) {
            return false;
        }

        return $strategy->isRetryable($envelope, $e);
    }

    /**
     * Adds stamps to the envelope by keeping only the First + Last N stamps.
     */
    private function withLimitedHistory(Envelope $envelope, StampInterface ...$stamps): Envelope
    {
        foreach ($stamps as $stamp) {
            $history = $envelope->all($stamp::class);
            if (\count($history) < $this->historySize) {
                $envelope = $envelope->with($stamp);
                continue;
            }

            $history = \array_merge(
                [$history[0]],
                \array_slice($history, -$this->historySize + 2),
                [$stamp],
            );

            $envelope = $envelope->withoutAll($stamp::class)->with(...$history);
        }

        return $envelope;
    }

    private function getRetryStrategy(\Throwable $exception, \ReflectionMethod $handler): ?RetryStrategyInterface
    {
        $attrs = $handler->getAttributes(RetryStrategy::class);
        $attribute = \count($attrs) > 0 ? $attrs[0]->newInstance() : null;

        if ($attribute === null) {
            $attrs = $handler->getDeclaringClass()->getAttributes(RetryStrategy::class);
            $attribute = \count($attrs) > 0 ? $attrs[0]->newInstance() : null;
        }

        return $attribute?->getRetryStrategy();
    }
}
