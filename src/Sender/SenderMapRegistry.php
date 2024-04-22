<?php

declare(strict_types=1);

namespace Spiral\Messenger\Sender;

use Spiral\Core\Attribute\Singleton;
use Spiral\Messenger\Attribute\TargetSender;
use Spiral\Tokenizer\Attribute\TargetAttribute;
use Spiral\Tokenizer\TokenizationListenerInterface;

#[TargetAttribute(TargetSender::class)]
#[Singleton]
final class SenderMapRegistry implements SenderMapRegistryInterface, SendersProviderInterface,
                                         TokenizationListenerInterface
{
    public function __construct(
        private array $senders = [],
    ) {
    }

    public function register(string $handler, string $sender): void
    {
        $this->senders[$handler] = $sender;
    }

    public function getSenders(): array
    {
        return $this->senders;
    }

    public function listen(\ReflectionClass $class): void
    {
        $attributes = $class->getAttributes(TargetSender::class);
        if ($attributes === []) {
            return;
        }

        /** @var TargetSender $sender */
        $sender = $attributes[0]->newInstance();
        $this->register($class->getName(), $sender->name);
    }

    public function finalize(): void
    {
        // do nothing
    }
}
