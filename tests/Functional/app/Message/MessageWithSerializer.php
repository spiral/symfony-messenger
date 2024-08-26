<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\Functional\App\Message;

use Spiral\Messenger\Attribute\Serializer;

#[Serializer(serializer: 'protobuf')]
final class MessageWithSerializer
{

}
