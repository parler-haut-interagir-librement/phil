<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Exception\InvalidChoice;
use Phil\CalendarBundle\Domain\Model\CalendarChoice;
use Phil\CalendarBundle\Domain\Repository\CalendarChoiceRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Psr\Clock\ClockInterface;

/**
 * The sources a member follows in their calendar and those they hide: hosts' calendars, tags, venues and events, kept
 * for each member by `kind:reference`, `MAX` at most. Following a source hidden shows it again, and the other way.
 */
final readonly class CalendarChoices
{
    /** The choices a member keeps at most. */
    public const int MAX = 200;

    public function __construct(
        private CalendarChoiceRepository $choices,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidChoice a value its source does not name, or a choice past `MAX`
     */
    public function follow(Member $member, ChoiceSource $source, string $value): CalendarChoice
    {
        return $this->choose($member, $source, $value, false);
    }

    /**
     * @throws InvalidChoice a value its source does not name, or a choice past `MAX`
     */
    public function hide(Member $member, ChoiceSource $source, string $value): CalendarChoice
    {
        return $this->choose($member, $source, $value, true);
    }

    /**
     * The choice of a draft from `CalendarChoiceType`, once it is valid.
     *
     * @throws InvalidChoice
     */
    public function apply(Member $member, CalendarChoiceDraft $draft): CalendarChoice
    {
        $source = ChoiceSource::tryFrom((string) $draft->source) ?? throw InvalidChoice::because('A choice follows or hides a host, a tag, a venue or an event.');

        return $this->choose($member, $source, (string) $draft->value, $draft->hidden);
    }

    /**
     * Neither followed nor hidden any more.
     */
    public function forget(Member $member, ChoiceSource $source, string $value): void
    {
        $choice = $this->choices->of($member, $source, $value);

        if ($choice instanceof CalendarChoice) {
            $this->choices->remove($choice);
        }
    }

    public function of(Member $member): MemberChoices
    {
        return new MemberChoices($this->choices->choicesOf($member, self::MAX));
    }

    /**
     * Forgets every choice of a member.
     */
    public function forgetMember(Member $member): void
    {
        $this->choices->forgetMember($member);
    }

    private function choose(Member $member, ChoiceSource $source, string $value, bool $hidden): CalendarChoice
    {
        $value = $source->checked($value);
        $now = DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new DateTimeZone('UTC'));
        $choice = $this->choices->of($member, $source, $value);

        if ($choice instanceof CalendarChoice) {
            $choice->change($hidden, $now);
        } elseif ($this->choices->count($member) >= self::MAX) {
            throw InvalidChoice::because(\sprintf('A member follows or hides %d sources at most.', self::MAX));
        } else {
            $choice = new CalendarChoice($member, $source, $value, $hidden, $now);
        }

        $this->choices->save($choice);

        return $choice;
    }
}
