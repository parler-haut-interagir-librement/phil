<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\SettingsBundle\Domain\ValueObject\Subject;

/**
 * Where the values that differ from their default are kept, one per subject and key, encoded as JSON.
 */
interface SettingRepository
{
    /**
     * @return array<string, string> the encoded values of the subject, by key
     */
    public function values(Subject $subject): array;

    /**
     * Writes the values given and removes the keys given, at once.
     *
     * @param array<string, string> $values  encoded, by key
     * @param list<string>          $removed the keys back to their default
     */
    public function save(Subject $subject, array $values, array $removed, DateTimeImmutable $at): void;

    /**
     * Removes every value of the subject.
     *
     * @return list<string> the keys removed
     */
    public function erase(Subject $subject): array;
}
