<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Fixtures;

use Override;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Model\CalendarChoice;
use Phil\CalendarBundle\Domain\Repository\CalendarChoiceRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;

final class InMemoryCalendarChoices implements CalendarChoiceRepository
{
    /** @var array<string, CalendarChoice> */
    public array $choices = [];

    /** How many times a choice was saved. */
    public int $saves = 0;

    /** How many times a choice was looked up. */
    public int $lookups = 0;

    #[Override]
    public function save(CalendarChoice $choice): void
    {
        $this->choices[$choice->id] = $choice;
        ++$this->saves;
    }

    #[Override]
    public function remove(CalendarChoice $choice): void
    {
        unset($this->choices[$choice->id]);
    }

    #[Override]
    public function of(Member $member, ChoiceSource $source, string $value): ?CalendarChoice
    {
        ++$this->lookups;

        return array_find($this->choices, static fn (CalendarChoice $choice): bool => $choice->member()->equals($member) && $choice->source() === $source && $choice->value() === $value);
    }

    #[Override]
    public function choicesOf(Member $member, int $limit): array
    {
        return \array_slice(array_values(array_filter($this->choices, static fn (CalendarChoice $choice): bool => $choice->member()->equals($member))), 0, $limit);
    }

    #[Override]
    public function count(Member $member): int
    {
        return \count($this->choicesOf($member, \PHP_INT_MAX));
    }

    #[Override]
    public function forgetMember(Member $member): void
    {
        $this->choices = array_filter($this->choices, static fn (CalendarChoice $choice): bool => !$choice->member()->equals($member));
    }
}
