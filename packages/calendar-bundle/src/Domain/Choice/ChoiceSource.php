<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Choice;

use Phil\CalendarBundle\Domain\Exception\InvalidChoice;

/**
 * What a member follows or hides in their calendar: a host's calendar, by `kind:reference`; a tag; a venue or an event,
 * by its identifier.
 */
enum ChoiceSource: string
{
    case Host = 'host';
    case Tag = 'tag';
    case Venue = 'venue';
    case Event = 'event';

    /** The longest value a choice keeps: a host, `kind:reference`, of 64 characters each. */
    public const int MAX_LENGTH = 129;

    /**
     * The value checked for this source: a host as hosts are named, a tag of 1 to 100 characters without control
     * characters, an identifier of 36 characters.
     *
     * @throws InvalidChoice
     */
    public function checked(string $value): string
    {
        $valid = match ($this) {
            self::Host => 1 === preg_match('/^[\w.:-]{1,64}:[\w.:-]{1,64}$/D', $value) && \strlen($value) <= self::MAX_LENGTH,
            self::Tag => '' !== trim($value) && mb_strlen($value) <= 100 && 1 !== preg_match('/[\x00-\x1F\x7F]/', $value),
            self::Venue, self::Event => 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $value),
        };

        return $valid ? $value : throw InvalidChoice::because(\sprintf('"%s" is not a %s a member can follow or hide.', mb_substr($value, 0, 100), $this->value));
    }
}
