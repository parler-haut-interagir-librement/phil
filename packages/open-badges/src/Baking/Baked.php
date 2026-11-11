<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Baking;

use JsonException;
use Phil\OpenBadges\Exception\InvalidImage;

/**
 * What an image may hold as a credential: a compact JSON Web Token, or the JSON of a document with an embedded proof.
 *
 * @internal
 */
final class Baked
{
    /**
     * The longest credential baked, in bytes.
     */
    public const int MAX_LENGTH = 1_048_576;

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed>|string $credential
     */
    public static function text(array|string $credential): string
    {
        return self::check(\is_array($credential) ? json_encode($credential, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) : $credential);
    }

    public static function isJwt(string $text): bool
    {
        return 1 === preg_match('/^[\w-]+\.[\w-]+\.[\w-]*$/D', $text);
    }

    public static function check(string $text): string
    {
        if (\strlen($text) > self::MAX_LENGTH) {
            throw InvalidImage::because(\sprintf('A baked credential is at most %d bytes.', self::MAX_LENGTH));
        }

        if (self::isJwt($text)) {
            return $text;
        }

        try {
            $document = json_decode($text, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $document = null;
        }

        if (!\is_array($document) || !\is_array($document['proof'] ?? null)) {
            throw InvalidImage::because('A baked credential is a compact JSON Web Token or a document with its proof.');
        }

        return $text;
    }
}
