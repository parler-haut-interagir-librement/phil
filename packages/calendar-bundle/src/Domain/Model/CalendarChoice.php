<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Symfony\Component\Uid\Uuid;

/**
 * A source a member follows in their calendar, or hides from it: one row per member, source and value. The member is
 * kept by its opaque `kind:reference`; when the choice was made, as a moment in UTC.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_calendar_choice_unique', columns: ['member', 'source', 'value'])]
final class CalendarChoice
{
    public const string TABLE = 'phil_calendar_choice';

    #[ORM\Id]
    #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
    public readonly string $id;

    #[ORM\Column(name: 'member', length: 129)]
    private readonly string $member;

    #[ORM\Column(name: 'source', length: 16)]
    private readonly string $source;

    #[ORM\Column(name: 'value', length: ChoiceSource::MAX_LENGTH)]
    private readonly string $value;

    public function __construct(Member $member, ChoiceSource $source, string $value, #[ORM\Column(name: 'hidden')]
        private bool $hidden, #[ORM\Column(name: 'chosen_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $chosenAt)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->member = $member->toString();
        $this->source = $source->value;
        $this->value = $source->checked($value);
    }

    public function member(): Member
    {
        return Member::fromString($this->member);
    }

    public function source(): ChoiceSource
    {
        return ChoiceSource::from($this->source);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * True for a source hidden, false for one followed.
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function chosenAt(): DateTimeImmutable
    {
        return $this->chosenAt;
    }

    /**
     * Followed or hidden again, from now.
     */
    public function change(bool $hidden, DateTimeImmutable $at): void
    {
        $this->hidden = $hidden;
        $this->chosenAt = $at;
    }
}
