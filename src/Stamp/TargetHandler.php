<?php

declare(strict_types=1);

namespace Spiral\Messenger\Stamp;

use Spiral\Interceptors\Context\Target;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class TargetHandler implements StampInterface
{
    public function __construct(
        public readonly Target $target,
    ) {}
}
