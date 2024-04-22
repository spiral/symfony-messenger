<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\App;

use Spiral\Core\Attribute\Proxy;
use Spiral\Messenger\Attribute\HandlerMethod;
use Spiral\Messenger\Attribute\RetryStrategy;
use Spiral\Messenger\ContextInterface;
use Spiral\Messenger\Tests\App\Message\Message;
use Spiral\Messenger\Tests\App\Message\MessageWithSerializer;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

final class Handler
{
    public function __construct(
        #[Proxy] private readonly ContextInterface $context,
    ) {
    }

    #[HandlerMethod]
    #[RetryStrategy(
        maxAttempts: 3,
        delay: 1,
        multiplier: 2,
    )]
    public function handleMessageWithSerializer(MessageWithSerializer $message): void
    {
        throw new RecoverableMessageHandlingException('Failed to handle message');
    }

    #[HandlerMethod]
    public function handleMessage(Message $message): void
    {
        $message->envelope = $this->context->getEnvelope();
    }
}
