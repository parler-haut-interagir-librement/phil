<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo;

use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use SensitiveParameter;

/**
 * What a participant tells the people responsible for an event so that it goes safely: who to call, allergies,
 * needs during the event. Only what serves safety during the event, never a medical record: no diagnosis field, no
 * list of medications.
 *
 * Its fields are never dumped: `var_dump()` and the debuggers show `[sensitive]`.
 */
final readonly class SafetyInfo
{
    public const int MAX_CONTACTS = 3;

    public const int MAX_FIELD = 2_000;

    /** @var list<EmergencyContact> */
    public array $contacts;

    public string $allergies;

    public string $needs;

    public string $notes;

    /**
     * @param list<EmergencyContact> $contacts  at most 3
     * @param string                 $allergies allergies and what to do about them
     * @param string                 $needs     needs during the event: medical, dietary, of access, of mobility
     * @param string                 $notes     anything else the participant wants the responsible people to know
     */
    public function __construct(
        #[SensitiveParameter]
        array $contacts = [],
        #[SensitiveParameter]
        string $allergies = '',
        #[SensitiveParameter]
        string $needs = '',
        #[SensitiveParameter]
        string $notes = '',
    ) {
        if (\count($contacts) > self::MAX_CONTACTS) {
            throw InvalidSafetyInfo::because(\sprintf('A participant names at most %d emergency contacts.', self::MAX_CONTACTS));
        }

        $this->contacts = $contacts;
        $this->allergies = $this->field($allergies, 'allergies');
        $this->needs = $this->field($needs, 'needs');
        $this->notes = $this->field($notes, 'notes');

        if ($this->isEmpty()) {
            throw InvalidSafetyInfo::because('Safety information holds at least one contact or one field; erase it instead of sending it empty.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['contacts' => '[sensitive]', 'allergies' => '[sensitive]', 'needs' => '[sensitive]', 'notes' => '[sensitive]'];
    }

    private function isEmpty(): bool
    {
        return [] === $this->contacts && '' === $this->allergies && '' === $this->needs && '' === $this->notes;
    }

    private function field(string $value, string $name): string
    {
        $value = trim($value);

        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > self::MAX_FIELD || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw InvalidSafetyInfo::because(\sprintf('The field "%s" holds at most %d characters of text.', $name, self::MAX_FIELD));
        }

        return $value;
    }
}
