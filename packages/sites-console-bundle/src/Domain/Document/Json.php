<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Document;

use JsonException;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;

/**
 * The JSON of the protocol, read and written with bounds: a size, a depth, an object at the top.
 */
final readonly class Json
{
    /** The largest document read: a status lists at most a few thousand packages. */
    public const int MAX_BYTES = 1_048_576;

    private const int MAX_DEPTH = 8;

    /**
     * @return array<array-key, mixed>
     *
     * @throws MalformedDocument
     */
    public static function decodeObject(string $json, int $maxBytes = self::MAX_BYTES): array
    {
        if (\strlen($json) > $maxBytes) {
            throw MalformedDocument::because(\sprintf('The document is larger than %d bytes.', $maxBytes));
        }

        try {
            $value = json_decode($json, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw MalformedDocument::because('The document is not JSON within the allowed depth.');
        }

        return Fields::object($value, 'document');
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function encode(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
    }
}
