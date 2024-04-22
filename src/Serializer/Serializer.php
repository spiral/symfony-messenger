<?php

declare(strict_types=1);

namespace Spiral\Messenger\Serializer;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

final class Serializer implements SerializerInterface
{
    public const MESSENGER_SERIALIZATION_CONTEXT = 'messenger_serialization';
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    private array $context;

    public function __construct(
        private readonly SymfonySerializerInterface $serializer,
        private readonly string $format = 'json',
        array $context = [],
    ) {
        $this->context = $context + [self::MESSENGER_SERIALIZATION_CONTEXT => true];
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body']) || empty($encodedEnvelope['headers'])) {
            throw new MessageDecodingFailedException(
                'Encoded envelope should have at least a "body" and some "headers", or maybe you should implement your own serializer.',
            );
        }

        if (empty($encodedEnvelope['headers']['type'])) {
            throw new MessageDecodingFailedException('Encoded envelope does not have a "type" header.');
        }

        $stamps = $this->decodeStamps($encodedEnvelope);
        $stamps[] = new SerializedMessageStamp($encodedEnvelope['body']);

        $serializerStamp = $this->findFirstSerializerStamp($stamps);

        $context = $this->context;
        if (null !== $serializerStamp) {
            $context = $serializerStamp->getContext() + $context;
        }

        $format = $context['serializer'] ?? $this->format;

        try {
            $message = $this->serializer->deserialize(
                $encodedEnvelope['body'],
                $encodedEnvelope['headers']['type'],
                $format,
                $context,
            );
        } catch (ExceptionInterface $e) {
            throw new MessageDecodingFailedException(
                'Could not decode message: ' . $e->getMessage(), $e->getCode(), $e,
            );
        }

        return new Envelope($message, $stamps);
    }

    public function encode(Envelope $envelope): array
    {
        $context = $this->context;
        /** @var SerializerStamp|null $serializerStamp */
        if ($serializerStamp = $envelope->last(SerializerStamp::class)) {
            $context = $serializerStamp->getContext() + $context;
        }

        /** @var SerializedMessageStamp|null $serializedMessageStamp */
        $serializedMessageStamp = $envelope->last(SerializedMessageStamp::class);

        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        $format = $context['serializer'] ?? $this->format;

        $headers = ['type' => $envelope->getMessage()::class] + $this->encodeStamps(
                $envelope,
            ) + $this->getContentTypeHeader($format);


        return [
            'body' => $serializedMessageStamp
                ? $serializedMessageStamp->getSerializedMessage()
                : $this->serializer->serialize($envelope->getMessage(), $format, $context),
            'headers' => $headers,
        ];
    }

    private function decodeStamps(array $encodedEnvelope): array
    {
        $stamps = [];
        foreach ($encodedEnvelope['headers'] as $name => $value) {
            if (!str_starts_with($name, self::STAMP_HEADER_PREFIX)) {
                continue;
            }

            try {
                $stamps[] = $this->serializer->deserialize(
                    $value,
                    substr($name, \strlen(self::STAMP_HEADER_PREFIX)) . '[]',
                    $this->format,
                    $this->context,
                );
            } catch (ExceptionInterface $e) {
                throw new MessageDecodingFailedException(
                    'Could not decode stamp: ' . $e->getMessage(),
                    $e->getCode(),
                    $e,
                );
            }
        }
        if ($stamps) {
            $stamps = array_merge(...$stamps);
        }

        return $stamps;
    }

    private function encodeStamps(Envelope $envelope): array
    {
        if (!$allStamps = $envelope->all()) {
            return [];
        }

        $headers = [];
        foreach ($allStamps as $class => $stamps) {
            $headers[self::STAMP_HEADER_PREFIX . $class] = $this->serializer->serialize(
                $stamps,
                $this->format,
                $this->context,
            );
        }

        return $headers;
    }

    /**
     * @param StampInterface[] $stamps
     */
    private function findFirstSerializerStamp(array $stamps): ?SerializerStamp
    {
        foreach ($stamps as $stamp) {
            if ($stamp instanceof SerializerStamp) {
                return $stamp;
            }
        }

        return null;
    }

    private function getContentTypeHeader(string $format): array
    {
        $mimeType = $this->getMimeTypeForFormat($format);

        return null === $mimeType ? [] : ['Content-Type' => $mimeType];
    }

    private function getMimeTypeForFormat(string $format): ?string
    {
        return match ($format) {
            'protobuf', 'proto' => 'application/protobuf',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'yml',
            'yaml' => 'application/x-yaml',
            'csv' => 'text/csv',
            default => null,
        };
    }
}
