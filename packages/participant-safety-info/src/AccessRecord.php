<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo;

use DateTimeImmutable;
use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;

/**
 * One reading of a participant's safety information by someone else, written to the journal before the information
 * is opened: who read, why, when, and the reason they gave when they broke the glass.
 */
final readonly class AccessRecord
{
    public const int MIN_JUSTIFICATION = 10;

    public const int MAX_JUSTIFICATION = 500;

    public function __construct(
        public string $event,
        public string $participant,
        public string $reader,
        public AccessReason $reason,
        public DateTimeImmutable $at,
        public ?string $justification = null,
    ) {
        Identifier::check($event, 'An event');
        Identifier::check($participant, 'A participant');
        Identifier::check($reader, 'A reader');

        if ((AccessReason::BreakGlass === $reason) !== (null !== $justification)) {
            throw InvalidSafetyInfo::because('A reading carries a justification when, and only when, it breaks the glass.');
        }

        if (null !== $justification && !$this->isJustification($justification)) {
            throw InvalidSafetyInfo::because(\sprintf('The reason for breaking the glass holds %d to %d characters.', self::MIN_JUSTIFICATION, self::MAX_JUSTIFICATION));
        }
    }

    private function isJustification(string $justification): bool
    {
        if (!mb_check_encoding($justification, 'UTF-8') || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $justification)) {
            return false;
        }

        $length = mb_strlen(trim($justification));

        return $length >= self::MIN_JUSTIFICATION && $length <= self::MAX_JUSTIFICATION;
    }
}
