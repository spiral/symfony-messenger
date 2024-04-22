<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\App\Message;

use Spiral\Messenger\Attribute\Async;
use Symfony\Component\Messenger\Envelope;

#[Async]
final class Message extends AbstractMessage
{
    public ?Envelope $envelope = null;
}
