<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Consent;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * A choice a person made, dated, against one version of the text: the cookie carries it and the proof records it.
 *
 * Nothing here names the person. The identifier is drawn at random for this choice alone, and links the cookie to its
 * proof and to nothing else.
 */
final readonly class ConsentChoice
{
    public const string ID_PATTERN = '/^[0-9a-f]{32}$/D';

    /**
     * @param list<string> $granted the optional categories granted; empty, every one is refused
     */
    public function __construct(
        public string $id,
        public string $version,
        public array $granted,
        public DateTimeImmutable $decidedAt,
        public bool $globalPrivacyControl = false,
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidPrivacyValue::because('A consent identifier is 32 lowercase hexadecimal characters.');
        }

        if (1 !== preg_match(ConsentPolicy::VERSION_PATTERN, $version)) {
            throw InvalidPrivacyValue::because('This is not a version of the consent text.');
        }

        foreach ($granted as $name) {
            if (1 !== preg_match(ConsentCategory::NAME_PATTERN, $name)) {
                throw InvalidPrivacyValue::because('This is not a consent category.');
            }
        }
    }

    public function grants(string $category): bool
    {
        return \in_array($category, $this->granted, true);
    }

    public function decidedAtTimestamp(): int
    {
        return $this->decidedAt->getTimestamp();
    }

    public static function dateOf(int $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$timestamp)->setTimezone(new DateTimeZone('UTC'));
    }
}
