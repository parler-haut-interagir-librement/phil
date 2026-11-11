<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use DateTimeImmutable;
use DateTimeZone;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a flag says about itself: what it guards, who owns it, its kind, and the day it should be gone.
 *
 * None of it changes an evaluation. **A flag is inventory**: each one doubles the paths through the code it
 * guards, and the expiry date is what lets a listing, or a test that fails past the date, say which ones have
 * outstayed their purpose.
 */
final readonly class FlagMetadata
{
    public const int DESCRIPTION_MAX_LENGTH = 500;

    public const int OWNER_MAX_LENGTH = 100;

    private function __construct(
        public ?string $description,
        public ?string $owner,
        public ?FlagKind $kind,
        public ?DateTimeImmutable $expiresOn,
    ) {
    }

    /**
     * @param DateTimeImmutable|null $expiresOn the day the flag should be gone; only its date counts, read in UTC
     */
    public static function of(
        ?string $description = null,
        ?string $owner = null,
        ?FlagKind $kind = null,
        ?DateTimeImmutable $expiresOn = null,
    ): self {
        return new self(
            self::text($description, 'description', self::DESCRIPTION_MAX_LENGTH),
            self::text($owner, 'owner', self::OWNER_MAX_LENGTH),
            $kind,
            $expiresOn?->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0),
        );
    }

    public static function none(): self
    {
        return new self(null, null, null, null);
    }

    /**
     * Whether the expiry day has come: a flag that expires on the 31st is expired from the 31st at midnight UTC.
     */
    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresOn instanceof DateTimeImmutable && $now >= $this->expiresOn;
    }

    private static function text(?string $value, string $what, int $maxLength): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            throw InvalidValue::because(\sprintf('The %s of a flag must be valid UTF-8.', $what));
        }

        $trimmed = trim($value);

        if ('' === $trimmed) {
            return null;
        }

        if (mb_strlen($trimmed) > $maxLength) {
            throw InvalidValue::because(\sprintf('The %s of a flag is at most %d characters.', $what, $maxLength));
        }

        return $trimmed;
    }
}
