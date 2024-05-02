<?php

declare(strict_types=1);

namespace Spiral\Messenger\Serializer;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

final readonly class StampSerializer
{
    public const MESSENGER_SERIALIZATION_CONTEXT = 'messenger_serialization';
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';
    private array $context;

    public function __construct(
        private readonly SymfonySerializerInterface $serializer,
        private readonly HeaderContext $headerContext,
    ) {
    }

    public function decodeStamps(array $encodedEnvelope): array
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
                    $this->headerContext->format,
                    $this->headerContext->context,
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

    /**
     * @return array<non-empty-string, string>
     */
    public function encodeStamps(Envelope $envelope): array
    {
        if (!$allStamps = $envelope->all()) {
            return [];
        }

        $headers = [];
        foreach ($allStamps as $class => $stamps) {
            $headers[self::STAMP_HEADER_PREFIX . $class] = $this->serializer->serialize(
                $stamps,
                $this->headerContext->format,
                $this->headerContext->context,
            );
        }

        return $headers;
    }

}
