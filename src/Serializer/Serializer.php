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

final readonly class Serializer implements SerializerInterface
{
    public function __construct(
        private SymfonySerializerInterface $serializer,
        private StampSerializer $stampSerializer,
        private BodyContext $bodyContext,
    ) {
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

        $stamps = $this->stampSerializer->decodeStamps($encodedEnvelope);
        $stamps[] = new SerializedMessageStamp($encodedEnvelope['body']);

        $serializerStamp = $this->findFirstSerializerStamp($stamps);

        $context = $this->bodyContext->context;
        if (null !== $serializerStamp) {
            $context = $serializerStamp->getContext() + $context;
        }

        /** @var non-empty-string $format */
        $format = $context['serializer'] ?? $this->bodyContext->format;

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
        $context = $this->bodyContext->context;
        /** @var SerializerStamp|null $serializerStamp */
        if ($serializerStamp = $envelope->last(SerializerStamp::class)) {
            $context = $serializerStamp->getContext() + $context;
        }

        /** @var SerializedMessageStamp|null $serializedMessageStamp */
        $serializedMessageStamp = $envelope->last(SerializedMessageStamp::class);

        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        /** @var non-empty-string $format */
        $format = $context['serializer'] ?? $this->bodyContext->format;

        $headers = ['type' => $envelope->getMessage()::class] + $this->stampSerializer->encodeStamps(
                $envelope,
            ) + $this->getContentTypeHeader($format);


        return [
            'body' => $serializedMessageStamp
                ? $serializedMessageStamp->getSerializedMessage()
                : $this->serializer->serialize($envelope->getMessage(), $format, $context),
            'headers' => $headers,
        ];
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
