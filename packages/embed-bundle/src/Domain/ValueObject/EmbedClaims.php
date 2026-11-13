<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Domain\ValueObject;

use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;

/**
 * What an embed token says: which embed, for which origin, in which theme, about which subject — the calendar or the
 * form the page shows, an identifier of the application —, when it was issued and until when it holds.
 */
final readonly class EmbedClaims
{
    public const string NAME = '/^[a-z][a-z0-9_-]{0,63}$/D';

    public const string THEME = '/^[a-z][a-z0-9-]{0,31}$/D';

    public const int MAX_SUBJECT = 200;

    private function __construct(
        public string $embed,
        public Origin $origin,
        public ?string $theme,
        public ?string $subject,
        public int $issuedAt,
        public ?int $expiresAt,
    ) {
    }

    /**
     * @throws InvalidEmbed when a name, the theme or the subject is malformed, or the token would expire when issued
     */
    public static function of(string $embed, Origin $origin, ?string $theme, ?string $subject, int $issuedAt, ?int $expiresAt = null): self
    {
        if (1 !== preg_match(self::NAME, $embed)) {
            throw InvalidEmbed::because(\sprintf('"%s" is not the name of an embed: a lowercase letter, then up to 63 lowercase letters, digits, "_" or "-".', $embed));
        }

        if (null !== $theme && 1 !== preg_match(self::THEME, $theme)) {
            throw InvalidEmbed::because(\sprintf('"%s" is not the name of a theme: a lowercase letter, then up to 31 lowercase letters, digits or "-".', $theme));
        }

        if (null !== $subject && ('' === $subject || \strlen($subject) > self::MAX_SUBJECT || 1 === preg_match('/[\x00-\x1f\x7f]/', $subject))) {
            throw InvalidEmbed::because(\sprintf('The subject of an embed is 1 to %d bytes without control characters.', self::MAX_SUBJECT));
        }

        if ($issuedAt < 0 || (null !== $expiresAt && $expiresAt <= $issuedAt)) {
            throw InvalidEmbed::because('An embed token expires after it is issued.');
        }

        return new self($embed, $origin, $theme, $subject, $issuedAt, $expiresAt);
    }
}
