<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo;

use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use SensitiveParameter;

/**
 * A person to call in an emergency: a name, how they relate to the participant, a telephone number.
 */
final readonly class EmergencyContact
{
    public const int MAX_NAME = 120;

    public const int MAX_RELATION = 60;

    public string $name;

    public string $relation;

    public string $phone;

    public function __construct(
        #[SensitiveParameter]
        string $name,
        #[SensitiveParameter]
        string $phone,
        #[SensitiveParameter]
        string $relation = '',
    ) {
        $name = trim($name);
        $relation = trim($relation);
        $phone = trim($phone);

        if ('' === $name || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > self::MAX_NAME || 1 === preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw InvalidSafetyInfo::because(\sprintf('The name of an emergency contact holds 1 to %d characters on one line.', self::MAX_NAME));
        }

        if (!mb_check_encoding($relation, 'UTF-8') || mb_strlen($relation) > self::MAX_RELATION || 1 === preg_match('/[\x00-\x1F\x7F]/', $relation)) {
            throw InvalidSafetyInfo::because(\sprintf('The relation of an emergency contact holds at most %d characters on one line.', self::MAX_RELATION));
        }

        // A number as people write it: an optional leading +, then digits, spaces, dots, dashes and parentheses.
        if (1 !== preg_match('/^\+?[0-9 ().\-]{3,32}$/', $phone) || 1 !== preg_match('/[0-9].*[0-9].*[0-9]/', $phone)) {
            throw InvalidSafetyInfo::because('The telephone number of an emergency contact holds 3 to 32 digits and separators.');
        }

        $this->name = $name;
        $this->relation = $relation;
        $this->phone = $phone;
    }

    /**
     * @return array{name: string, relation: string, phone: string}
     */
    public function __debugInfo(): array
    {
        return ['name' => '[sensitive]', 'relation' => '[sensitive]', 'phone' => '[sensitive]'];
    }
}
