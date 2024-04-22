<?php

declare(strict_types=1);

namespace Spiral\Messenger\Tests\App;

use Spiral\Boot\Bootloader\ConfigurationBootloader;
use Spiral\Boot\Bootloader\CoreBootloader;
use Spiral\Core\Container;
use Spiral\Messenger\Bootloader\MessengerBootloader;
use Spiral\Testing\TestableKernelInterface;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;

final class Kernel extends \Spiral\Framework\Kernel implements TestableKernelInterface
{
    protected function defineSystemBootloaders(): array
    {
        return [
            CoreBootloader::class,
            ConfigurationBootloader::class,
            TokenizerListenerBootloader::class,
        ];
    }

    public function defineBootloaders(): array
    {
        return [
            MessengerBootloader::class,
            // ...
        ];
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRegisteredDispatchers(): array
    {
        return $this->dispatchers;
    }

    public function getRegisteredBootloaders(): array
    {
        return $this->bootloader->getClasses();
    }
}
