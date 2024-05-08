<?php

declare(strict_types=1);

namespace Spiral\Messenger\Sender;

use Spiral\Core\Attribute\Singleton;
use Spiral\Messenger\Attribute\TargetSender;
use Spiral\Tokenizer\Attribute\TargetAttribute;
use Spiral\Tokenizer\TokenizationListenerInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

#[TargetAttribute(TargetSender::class)]
#[Singleton]
final class SenderMapRegistry implements SenderMapRegistryInterface, SendersProviderInterface,
                                         TokenizationListenerInterface
{
    /**
     * @param array<non-empty-string, list<class-string<SenderInterface>|non-empty-string>> $senders
     */
    public function __construct(
        private array $senders = [],
    ) {
    }

    public function register(string $handler, string $sender): void
    {
        // Deduplicate senders
        if (!\in_array($sender, $this->senders[$handler] ?? [], true)) {
            $this->senders[$handler][] = $sender;
        }
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
        // Do nothing
    }
}
