<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A link an application knows it wrote into an author's page, the only kind counted link by link.
 *
 * The page carries the reference, and never a URL reaches the collection: a query string can hold a token, and a
 * forged reference finds no link.
 */
final readonly class DeclaredOutboundLink
{
    public const int MAX_AUTHOR_LENGTH = 255;

    private function __construct(
        public string $reference,
        public string $author,
    ) {
    }

    /**
     * @param string $reference opaque, as long as a counter value can be
     * @param string $author    the application's opaque reference of the account the link belongs to
     */
    public static function of(string $reference, string $author): self
    {
        if ('' === $reference || \strlen($reference) > CounterKey::MAX_VALUE_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $reference)) {
            throw InvalidValue::because(\sprintf('A link reference is 1 to %d bytes, without control characters.', CounterKey::MAX_VALUE_LENGTH));
        }

        if ('' === $author || \strlen($author) > self::MAX_AUTHOR_LENGTH) {
            throw InvalidValue::because(\sprintf('An author reference is 1 to %d bytes.', self::MAX_AUTHOR_LENGTH));
        }

        return new self($reference, $author);
    }
}
