<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Repository;

use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Model\CalendarChoice;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * Where the choices of members' calendars are kept.
 */
interface CalendarChoiceRepository
{
    public function save(CalendarChoice $choice): void;

    public function remove(CalendarChoice $choice): void;

    public function of(Member $member, ChoiceSource $source, string $value): ?CalendarChoice;

    /**
     * A member's choices, in the order they were made, at most so many.
     *
     * @return list<CalendarChoice>
     */
    public function choicesOf(Member $member, int $limit): array;

    public function count(Member $member): int;

    /**
     * Removes every choice of a member.
     */
    public function forgetMember(Member $member): void;
}
