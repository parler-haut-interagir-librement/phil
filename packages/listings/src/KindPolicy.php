<?php

declare(strict_types=1);

namespace Phil\Listings;

use DateInterval;
use DateTimeImmutable;
use Phil\Listings\Exception\InvalidListing;

/**
 * The rules of one kind of listing: how long a listing stays published, when its author is reminded, how often it
 * may be extended, how it is moderated, how many reports hide it, the minimum age of its respondents and how many
 * open responses it takes.
 *
 * Every number is bounded, so that a policy read from configuration cannot make a listing live forever or take
 * unbounded responses.
 */
final readonly class KindPolicy
{
    public const int MAX_DAYS = 365;

    public const int MAX_EXTENSIONS = 52;

    public const int MAX_REPORTS_TO_HIDE = 100;

    public const int MAX_AGE = 120;

    public const int MAX_OPEN_RESPONSES = 10000;

    private function __construct(
        public ListingKind $kind,
        public int $lifetimeDays,
        public int $reminderDays,
        public int $extensionDays,
        public int $maxExtensions,
        public Moderation $moderation,
        public int $reportsToHide,
        public ?int $minimumAge,
        public bool $acceptsResponses,
        public int $maxOpenResponses,
    ) {
    }

    /**
     * @param int      $lifetimeDays     how long a listing stays published, from its publication
     * @param int      $reminderDays     how many days before its expiry its author is reminded to extend it; 0 for
     *                                   no reminder
     * @param int      $extensionDays    how many days one extension adds
     * @param int      $maxExtensions    how many times a listing may be extended; 0 for never
     * @param int      $reportsToHide    how many distinct members' reports hide a published listing
     * @param int|null $minimumAge       the youngest age that may respond to a listing of this kind; a listing may ask
     *                                   for an older one, never a younger one; null for none
     * @param int      $maxOpenResponses how many pending and accepted responses one listing takes
     *
     * @throws InvalidListing
     */
    public static function of(
        ListingKind $kind,
        int $lifetimeDays = 30,
        int $reminderDays = 3,
        int $extensionDays = 30,
        int $maxExtensions = 3,
        Moderation $moderation = Moderation::AfterPublication,
        int $reportsToHide = 3,
        ?int $minimumAge = null,
        bool $acceptsResponses = true,
        int $maxOpenResponses = 100,
    ): self {
        self::bound('The lifetime in days', $lifetimeDays, 1, self::MAX_DAYS);
        self::bound('The reminder in days', $reminderDays, 0, $lifetimeDays - 1);
        self::bound('The extension in days', $extensionDays, 1, self::MAX_DAYS);
        self::bound('The number of extensions', $maxExtensions, 0, self::MAX_EXTENSIONS);
        self::bound('The number of reports that hide a listing', $reportsToHide, 1, self::MAX_REPORTS_TO_HIDE);

        if (null !== $minimumAge) {
            self::bound('The minimum age', $minimumAge, 0, self::MAX_AGE);
        }

        self::bound('The number of open responses', $maxOpenResponses, 1, self::MAX_OPEN_RESPONSES);

        return new self($kind, $lifetimeDays, $reminderDays, $extensionDays, $maxExtensions, $moderation, $reportsToHide, $minimumAge, $acceptsResponses, $maxOpenResponses);
    }

    public function expiryFrom(DateTimeImmutable $start): DateTimeImmutable
    {
        return $start->add(new DateInterval(\sprintf('P%dD', $this->lifetimeDays)));
    }

    public function extendedFrom(DateTimeImmutable $start): DateTimeImmutable
    {
        return $start->add(new DateInterval(\sprintf('P%dD', $this->extensionDays)));
    }

    public function reminderFrom(DateTimeImmutable $expiry): DateTimeImmutable
    {
        return $expiry->sub(new DateInterval(\sprintf('P%dD', $this->reminderDays)));
    }

    private static function bound(string $what, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw InvalidListing::because(\sprintf('%s is from %d to %d; %d is not.', $what, $min, $max, $value));
        }
    }
}
