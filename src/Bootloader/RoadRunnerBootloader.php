<?php

declare(strict_types=1);

namespace Spiral\Messenger\Bootloader;

use RoadRunner\Lock\Lock;
use RoadRunner\Lock\LockInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\JobsInterface;

final class RoadRunnerBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            RPCInterface::class => static fn(EnvironmentInterface $env): RPCInterface => new RPC(
                Relay::create($env->getRPCAddress()),
            ),

            EnvironmentInterface::class => static fn(): EnvironmentInterface => Environment::fromGlobals(),

            JobsInterface::class => static fn(RPCInterface $rpc): JobsInterface => new Jobs($rpc),
            LockInterface::class => static fn(RPCInterface $rpc): LockInterface => new Lock($rpc),
        ];
    }
}
