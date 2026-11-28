<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Shifts\Shift\Shift;
use Phil\ShiftsBundle\Application\Contract\BirthDates;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Domain\Exception\InvalidGuardianContact;
use Phil\ShiftsBundle\Domain\Model\GuardianContact;
use Phil\ShiftsBundle\Domain\Repository\GuardianRepository;
use Psr\Clock\ClockInterface;

/**
 * The volunteers who are minors and the contact of their parent or guardian. A volunteer is a minor on a shift when,
 * on the day it starts in the configured time zone, they have not reached the age of majority, by the birth date the
 * application gives; a volunteer whose birth date the application does not give is not one. Without a source of birth
 * dates, nobody is a minor and nothing is asked.
 *
 * A minor joins a shift — by signing up, by interest, by assignment or by the swap board — only once the contact of a
 * guardian is recorded with their consent. Withdrawing a consent or forgetting a contact takes nobody off a shift.
 */
final readonly class Minors
{
    public const int AGE_OF_MAJORITY = 18;

    private DateTimeZone $zone;

    public function __construct(
        private GuardianRepository $guardians,
        private ClockInterface $clock,
        private ?BirthDates $birthDates = null,
        private int $ageOfMajority = self::AGE_OF_MAJORITY,
        string $timeZone = 'UTC',
    ) {
        $this->zone = new DateTimeZone($timeZone);
    }

    /**
     * Whether the bundle has a source of birth dates, without which nobody is a minor.
     */
    public function isEnabled(): bool
    {
        return $this->birthDates instanceof BirthDates;
    }

    /**
     * Whether the volunteer is a minor on the day of the instant, by default today.
     *
     * @phpstan-impure
     */
    public function isMinor(string $volunteer, ?DateTimeImmutable $on = null): bool
    {
        $birth = $this->birthDates?->of($volunteer);

        if (!$birth instanceof DateTimeImmutable) {
            return false;
        }

        $day = ($on ?? $this->clock->now())->setTimezone($this->zone)->format('Y-m-d');
        $majority = new DateTimeImmutable($birth->format('Y-m-d'), $this->zone)->modify(\sprintf('+%d years', $this->ageOfMajority));

        return $day < $majority->format('Y-m-d');
    }

    /**
     * Whether the volunteer may join the shift: of age on its day, or with the consent of a guardian recorded.
     *
     * @phpstan-impure
     */
    public function mayJoin(string $volunteer, Shift $shift): bool
    {
        return !$this->isMinor($volunteer, $shift->startsAt) || true === $this->guardians->find($volunteer)?->hasConsent();
    }

    /**
     * @throws RequestRefused with `shifts.guardian_consent` when the volunteer is a minor on the shift's day without
     *                        the consent of a guardian
     */
    public function assertMayJoin(string $volunteer, Shift $shift): void
    {
        if (!$this->mayJoin($volunteer, $shift)) {
            throw new RequestRefused(RequestRefused::GUARDIAN_CONSENT);
        }
    }

    /**
     * Records the contact of the volunteer's guardian, or corrects it; a consent already given is kept.
     *
     * @throws InvalidGuardianContact when a field is refused
     */
    public function record(string $volunteer, string $name, ?string $phone, ?string $email, string $recordedBy): GuardianContact
    {
        $contact = new GuardianContact($volunteer, $name, $phone, $email, $recordedBy);
        $known = $this->guardians->find($volunteer);

        if ($known?->consentedAt instanceof DateTimeImmutable && null !== $known->consentRecordedBy) {
            $contact = $contact->withConsent($known->consentedAt, $known->consentRecordedBy);
        }

        $this->guardians->save($contact);

        return $contact;
    }

    /**
     * Records that the guardian consented, now, as told to the person.
     *
     * @throws RequestRefused         with `shifts.no_guardian` when the volunteer has no contact recorded
     * @throws InvalidGuardianContact when the person is not an identifier
     */
    public function consent(string $volunteer, string $recordedBy): GuardianContact
    {
        $contact = ($this->guardians->find($volunteer) ?? throw new RequestRefused(RequestRefused::NO_GUARDIAN))->withConsent($this->clock->now(), $recordedBy);
        $this->guardians->save($contact);

        return $contact;
    }

    /**
     * Records that the guardian withdrew their consent; the contact remains.
     *
     * @throws RequestRefused with `shifts.no_guardian` when the volunteer has no contact recorded
     */
    public function withdrawConsent(string $volunteer): GuardianContact
    {
        $contact = ($this->guardians->find($volunteer) ?? throw new RequestRefused(RequestRefused::NO_GUARDIAN))->withoutConsent();
        $this->guardians->save($contact);

        return $contact;
    }

    /**
     * @phpstan-impure
     */
    public function contactOf(string $volunteer): ?GuardianContact
    {
        return $this->guardians->find($volunteer);
    }

    /**
     * Deletes the contact of the volunteer's guardian and its consent, once the volunteer is of age or leaves.
     */
    public function forget(string $volunteer): void
    {
        $this->guardians->remove($volunteer);
    }
}
