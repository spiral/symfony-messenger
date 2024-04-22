<?php

declare(strict_types=1);

namespace Spiral\Messenger\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Messenger\Config\MessengerConfig;
use Spiral\Messenger\Middleware\DetectPipelineMiddleware;
use Spiral\Messenger\Middleware\DetectProtobufSerializerMiddleware;
use Spiral\Messenger\Middleware\DetectSerializerMiddleware;
use Spiral\Messenger\Sender\RoadRunnerSender;

final class ConfigurationBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
        private readonly EnvironmentInterface $env,
    ) {
    }

    public function init(): void
    {
        $this->initConfig();
    }

    private function initConfig(): void
    {
        $this->config->setDefaults(
            MessengerConfig::CONFIG,
            [
                'defaultPipeline' => $this->env->get('MESSENGER_DEFAULT_PIPELINE'),
                'stampsHistorySize' => $this->env->get('MESSENGER_STAMPS_HISTORY_SIZE', 10),
                'middlewares' => [
                    new DetectProtobufSerializerMiddleware(),
                    new DetectSerializerMiddleware(),
                    new DetectPipelineMiddleware(),
                ],
                'senders' => [
                    'map' => [
                        '*' => [
                            RoadRunnerSender::class,
                        ],
                    ],
                ],
            ],
        );
    }
}
