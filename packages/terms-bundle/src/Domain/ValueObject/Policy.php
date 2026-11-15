<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Domain\ValueObject;

use DateInterval;
use DateTimeImmutable;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\Identifiers;

/**
 * A policy the application declares — its terms of use, its privacy policy, its code of conduct —: its key, whether a
 * signed-in member must have accepted its version in force to go on, after how many days an acceptance must be given
 * again, if ever, and whether each of its versions names the licence of the members' contributions.
 */
final readonly class Policy
{
    public const int MAX_RENEWAL_DAYS = 3650;

    /**
     * @throws InvalidTerms
     */
    public function __construct(
        public string $key,
        public bool $required = true,
        public ?int $renewAfterDays = null,
        public bool $contributionLicence = false,
    ) {
        Identifiers::policy($key);

        if (null !== $renewAfterDays && ($renewAfterDays < 1 || $renewAfterDays > self::MAX_RENEWAL_DAYS)) {
            throw InvalidTerms::because('renewal', 'An acceptance is renewed after 1 to 3650 days.');
        }
    }

    /**
     * The moment from which an acceptance still counts at a given moment: none without renewal.
     */
    public function acceptedSince(DateTimeImmutable $at): ?DateTimeImmutable
    {
        return null === $this->renewAfterDays ? null : $at->sub(new DateInterval('P'.$this->renewAfterDays.'D'));
    }
}
