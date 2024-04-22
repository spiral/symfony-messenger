<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\App\Message;

use Spiral\Messenger\Attribute\Async;
use Spiral\Messenger\Attribute\Pipeline;
use Spiral\Messenger\Attribute\TargetSender;

#[Pipeline(pipeline: 'kafka')]
final class MessageWithPipeline
{

}
