<?php

declare(strict_types=1);

namespace Spiral\Messenger\Exception;

use Spiral\RoadRunner\Jobs\OptionsInterface;

final class RetryException extends StateException
{
    public function __construct(
        string $reason = '',
        private ?OptionsInterface $options = null,
    ) {
        parent::__construct($reason);
    }

    public function getOptions(): ?OptionsInterface
    {
        return $this->options;
    }
}
