<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use DateTimeZone;
use Phil\GuardianConsent\Exception\InvalidConfiguration;
use Phil\GuardianConsent\Exception\InvalidConsent;
use Psr\Clock\ClockInterface;

/**
 * The rules of a place on the consent of minors: the age from which a person consents for themselves, the age of
 * majority, the time zone in which a birthday begins, and the day a person born on 29 February has one.
 *
 * The library ships one set of rules, Quebec's, as the default: 14 years for the consent, 18 for majority. Any other is
 * configured by the application, from the text of the law that applies to it.
 */
final readonly class Jurisdiction
{
    public const int MAX_AGE = 21;

    /** The oldest date of birth accepted, in years before today. */
    public const int MAX_LIFESPAN = 150;

    public const string QUEBEC = 'CA-QC';

    /**
     * @param string $code an ISO 3166 code, a country (`FR`) or a subdivision (`CA-QC`)
     *
     * @throws InvalidConfiguration when the code is not an ISO 3166 code, or an age is out of bounds
     */
    public function __construct(
        public string $code,
        public int $consentAge,
        public int $majorityAge,
        public DateTimeZone $timeZone,
        public LeapDayRule $leapDayRule = LeapDayRule::FirstOfMarch,
    ) {
        if (1 !== preg_match('/^[A-Z]{2}(?:-[A-Z0-9]{1,3})?$/D', $code)) {
            throw InvalidConfiguration::because('A jurisdiction is named by an ISO 3166 code, such as "FR" or "CA-QC".');
        }

        if ($consentAge < 1 || $consentAge > $majorityAge || $majorityAge > self::MAX_AGE) {
            throw InvalidConfiguration::because(\sprintf('The age of consent is from 1 to the age of majority, which is at most %d.', self::MAX_AGE));
        }
    }

    /**
     * Quebec: the holder of parental authority consents for a minor under 14; majority at 18.
     */
    public static function quebec(): self
    {
        return new self(self::QUEBEC, 14, 18, new DateTimeZone('America/Toronto'));
    }

    /**
     * The day it is in the jurisdiction's time zone.
     */
    public function today(ClockInterface $clock): CalendarDate
    {
        return CalendarDate::of($clock->now(), $this->timeZone);
    }

    /**
     * The day a person born on `$birthDate` reaches the age of consent.
     */
    public function consentAgeOn(CalendarDate $birthDate): CalendarDate
    {
        return $birthDate->anniversary($this->consentAge, $this->leapDayRule);
    }

    /**
     * A person's age today, from their date of birth, which it does not keep.
     *
     * @throws InvalidConsent when the date of birth is after today, or more than 150 years before
     */
    public function assess(CalendarDate $birthDate, ClockInterface $clock): AgeAssessment
    {
        $today = $this->today($clock);

        if ($birthDate->isAfter($today)) {
            throw InvalidConsent::because('A date of birth is not after today.');
        }

        $age = $birthDate->yearsUntil($today, $this->leapDayRule);

        if ($age > self::MAX_LIFESPAN) {
            throw InvalidConsent::because(\sprintf('A date of birth is at most %d years before today.', self::MAX_LIFESPAN));
        }

        $bracket = match (true) {
            $age < $this->consentAge => AgeBracket::BelowConsentAge,
            $age < $this->majorityAge => AgeBracket::MinorAtConsentAge,
            default => AgeBracket::Adult,
        };

        return new AgeAssessment(
            $this->code,
            $today,
            $age,
            $bracket,
            $this->consentAgeOn($birthDate),
            $birthDate->anniversary($this->majorityAge, $this->leapDayRule),
        );
    }
}
