<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\ShiftsBundle\Domain\Exception\InvalidGuardianContact;

/**
 * The parent or guardian a coordinator reaches about a volunteer who is a minor: a name and at least one way to reach
 * them — a telephone number, an email address, or both —, and their consent to the volunteering once given, with its
 * instant and the person who recorded it. Nothing else is kept: no address, no birth date, no relationship.
 */
final class GuardianContact
{
    public const int NAME_LENGTH = 120;

    public const int PHONE_LENGTH = 32;

    public const int EMAIL_LENGTH = 254;

    public const int PERSON_LENGTH = 191;

    public readonly string $name;

    public readonly ?string $phone;

    public readonly ?string $email;

    /**
     * @throws InvalidGuardianContact when a field is empty, too long or of the wrong shape, or no way to reach is given
     */
    public function __construct(
        public readonly string $volunteer,
        string $name,
        ?string $phone,
        ?string $email,
        public readonly string $recordedBy,
        public readonly ?DateTimeImmutable $consentedAt = null,
        public readonly ?string $consentRecordedBy = null,
    ) {
        $this->name = $this->name($name);
        $this->phone = $this->phone($phone);
        $this->email = $this->email($email);
        $this->person($volunteer);
        $this->person($recordedBy);

        if (null === $this->phone && null === $this->email) {
            throw new InvalidGuardianContact(InvalidGuardianContact::NO_WAY_TO_REACH);
        }

        if ((!$consentedAt instanceof DateTimeImmutable) !== (null === $consentRecordedBy)) {
            throw new InvalidGuardianContact(InvalidGuardianContact::PERSON);
        }

        if (null !== $consentRecordedBy) {
            $this->person($consentRecordedBy);
        }
    }

    public function hasConsent(): bool
    {
        return $this->consentedAt instanceof DateTimeImmutable;
    }

    /**
     * The same contact, the guardian's consent given at the instant and recorded by the person.
     */
    public function withConsent(DateTimeImmutable $at, string $recordedBy): self
    {
        return new self($this->volunteer, $this->name, $this->phone, $this->email, $this->recordedBy, $at, $recordedBy);
    }

    /**
     * The same contact, without the consent.
     */
    public function withoutConsent(): self
    {
        return new self($this->volunteer, $this->name, $this->phone, $this->email, $this->recordedBy);
    }

    private function name(string $name): string
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > self::NAME_LENGTH || 1 === preg_match('/[\p{Cc}\p{Cf}]/u', $name) || !mb_check_encoding($name, 'UTF-8')) {
            throw new InvalidGuardianContact(InvalidGuardianContact::NAME);
        }

        return $name;
    }

    private function phone(?string $phone): ?string
    {
        $phone = null === $phone ? '' : trim($phone);

        if ('' === $phone) {
            return null;
        }

        if (1 !== preg_match('/^\+?[0-9(][0-9 ().-]{2,30}[0-9]\z/', $phone) || \strlen($phone) > self::PHONE_LENGTH) {
            throw new InvalidGuardianContact(InvalidGuardianContact::PHONE);
        }

        return $phone;
    }

    private function email(?string $email): ?string
    {
        $email = null === $email ? '' : trim($email);

        if ('' === $email) {
            return null;
        }

        if (\strlen($email) > self::EMAIL_LENGTH || 1 !== preg_match('/^[^\s@\p{Cc}]{1,64}@[^\s@\p{Cc}]+\.[^\s@\p{Cc}.]+\z/u', $email)) {
            throw new InvalidGuardianContact(InvalidGuardianContact::EMAIL);
        }

        return $email;
    }

    private function person(string $person): void
    {
        if ('' === $person || \strlen($person) > self::PERSON_LENGTH || $person !== trim($person) || !mb_check_encoding($person, 'UTF-8')) {
            throw new InvalidGuardianContact(InvalidGuardianContact::PERSON);
        }
    }
}
