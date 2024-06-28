<?php

declare(strict_types=1);

namespace Spiral\Messenger\Bootloader;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Messenger\Config\MessengerConfig;
use Spiral\Messenger\Handler\HandlersLocator;
use Spiral\Messenger\Handler\HandlersRegistry;
use Spiral\Messenger\Handler\HandlersRegistryInterface;
use Spiral\Messenger\Middleware\MiddlewareRegistry;
use Spiral\Messenger\Middleware\MiddlewareRegistryInterface;
use Spiral\Messenger\Middleware\SendFailedMessageForRetryMiddleware;
use Spiral\Messenger\Pipeline\PipelineAliasesProviderInterface;
use Spiral\Messenger\Pipeline\PipelineProviderInterface;
use Spiral\Messenger\Pipeline\PipelineRegistryInterface;
use Spiral\Messenger\Pipeline\PipelinesProvider;
use Spiral\Messenger\Pipeline\RoadRunnerPipelineRegistry;
use Spiral\Messenger\RoutableMessageBus;
use Spiral\Messenger\Sender\RoadRunnerSender;
use Spiral\Messenger\Sender\SenderMapRegistry;
use Spiral\Messenger\Sender\SenderMapRegistryInterface;
use Spiral\Messenger\Sender\SendersProviderInterface;
use Spiral\Messenger\Serializer\BodyContext;
use Spiral\Messenger\Serializer\Serializer;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\Serializer\Config\SerializerConfig;
use Spiral\Serializer\Symfony\Bootloader\SerializerBootloader;
use Spiral\Tokenizer\TokenizerListenerRegistryInterface;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class MessengerBootloader extends Bootloader
{
    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
            ConfigurationBootloader::class,
            SerializerBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            PipelineRegistryInterface::class => RoadRunnerPipelineRegistry::class,
            PipelineAliasesProviderInterface::class => PipelinesProvider::class,
            PipelineProviderInterface::class => PipelinesProvider::class,

            SenderMapRegistryInterface::class => SenderMapRegistry::class,
            SendersProviderInterface::class => SenderMapRegistry::class,
            SenderMapRegistry::class => static fn(
                MessengerConfig $config,
            ): SenderMapRegistry => new SenderMapRegistry($config->getSendersMap()),

            MiddlewareRegistryInterface::class => MiddlewareRegistry::class,

            MessageBusInterface::class => static fn(
                ContainerInterface $container,
                MiddlewareRegistryInterface $registry,
                MessengerConfig $config,
            ): MessageBusInterface => new RoutableMessageBus(
                middlewareHandlers: $config->getRouterMiddlewares(),
                busLocator: $container,
                fallbackBus: new MessageBus($registry),
            ),

            HandlersLocatorInterface::class => HandlersLocator::class,
            HandlersRegistryInterface::class => HandlersRegistry::class,

            SerializerInterface::class => Serializer::class,
            BodyContext::class => static fn(SerializerConfig $config): BodyContext => new BodyContext(
                format: $config->getDefault(),
            ),

            RoadRunnerSender::class => static fn(
                JobsInterface $jobs,
                PipelineAliasesProviderInterface $aliases,
                SerializerInterface $serializer,
                ExceptionReporterInterface $reporter,
                MessengerConfig $config,
            ): RoadRunnerSender => new RoadRunnerSender(
                jobs: $jobs,
                serializer: $serializer,
                reporter: $reporter,
                aliases: [...$aliases->getAliases(), ...$config->getPipelineAliases()],
                defaultPipeline: $config->getDefaultPipeline(),
            ),
            SendersLocatorInterface::class => static fn(
                ContainerInterface $container,
                SendersProviderInterface $provider,
            ): SendersLocator =>
                // todo:
                // has sender interceptors?
                // return \Spiral\Messenger\Sender\SendersLocator
                // else
                new SendersLocator(
                    sendersMap: $provider->getSenders(),
                    sendersLocator: $container,
                )
        ];
    }

    public function init(
        TokenizerListenerRegistryInterface $registry,
        PipelinesProvider $listener,
        HandlersRegistry $handlersRegistry,
        SenderMapRegistry $sendersRegistry,
    ): void {
        $registry->addListener($listener);
        $registry->addListener($handlersRegistry);
        $registry->addListener($sendersRegistry);
    }

    public function boot(
        MiddlewareRegistryInterface $middlewareRegistry,
        HandlersLocatorInterface $handlersLocator,
        SendersLocatorInterface $sendersLocator,
        MessengerConfig $config,
    ): void {
        foreach ($this->getMiddlewares($handlersLocator, $sendersLocator, $config) as $priority => $middleware) {
            $middlewareRegistry->addMiddleware(middleware: $middleware, priority: $priority);
        }

        foreach ($config->getMiddlewares() as $middleware) {
            $middlewareRegistry->addMiddleware(middleware: $middleware);
        }
    }

    private function getMiddlewares(
        HandlersLocatorInterface $handlersLocator,
        SendersLocatorInterface $sendersLocator,
        MessengerConfig $config,
    ): iterable {
        yield MiddlewareRegistryInterface::LOW_PRIORITY => new SendFailedMessageForRetryMiddleware(
            sendersLocator: $sendersLocator,
            historySize: $config->getStampsHistorySize(),
        );

        yield MiddlewareRegistryInterface::LOW_PRIORITY => new SendMessageMiddleware(
            sendersLocator: $sendersLocator,
            allowNoSenders: true,
        );

        yield MiddlewareRegistryInterface::LOW_PRIORITY => new HandleMessageMiddleware(
            handlersLocator: $handlersLocator,
        );
    }
}
