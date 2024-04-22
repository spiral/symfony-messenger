<?php

declare(strict_types=1);

namespace Spiral\Messenger\Stamp;

use Spiral\RoadRunner\Jobs\OptionsInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class OptionsStamp implements StampInterface
{
    public function __construct(
        private readonly OptionsInterface $options,
    ) {
    }

    public function getOptions(): OptionsInterface
    {
        return $this->options;
    }
}
