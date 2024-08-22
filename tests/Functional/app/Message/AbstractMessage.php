<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\Functional\App\Message;

use Spiral\Messenger\Attribute\Async;
use Spiral\Messenger\Attribute\TargetSender;
use Symfony\Component\Messenger\Envelope;

#[TargetSender(name: 'sender')]
abstract class AbstractMessage
{
}
