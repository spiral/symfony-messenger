<?php

declare(strict_types=1);

namespace Spiral\Messenger\Sender;

use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

interface SendersProviderInterface
{
    /**
     * @return array<string, class-string<SenderInterface>|non-empty-string>
     */
    public function getSenders(): array;
}
