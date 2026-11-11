<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use DateTimeInterface;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * An `Event`, or one of its kinds — `Festival`, `MusicEvent`, `SocialEvent`, `EducationEvent`: what, when, where,
 * whether it holds, how it is attended, who organises it and what a ticket costs. A place given as a text is a
 * `Place` of that name.
 */
final class Event
{
    /**
     * @param Node|Reference|list<Node|Reference>|null $offers
     * @param Node|Reference|list<Node|Reference>|null $performer
     * @param array<string, mixed>                     $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        DateTimeInterface|string $startDate,
        string|Node|Reference $location,
        ?string $id = null,
        DateTimeInterface|string|null $endDate = null,
        string|LanguageString|null $description = null,
        ?string $url = null,
        string|Node|Reference|null $image = null,
        ?EventStatus $status = null,
        ?EventAttendanceMode $attendanceMode = null,
        Node|Reference|array|null $offers = null,
        Node|Reference|null $organizer = null,
        Node|Reference|array|null $performer = null,
        ?string $inLanguage = null,
        string $type = 'Event',
        array $properties = [],
    ): Node {
        return Node::of($type, Properties::merge($properties, [
            'name' => $name,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'location' => \is_string($location) ? Node::of('Place', ['name' => $location]) : $location,
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'eventStatus' => $status,
            'eventAttendanceMode' => $attendanceMode,
            'offers' => $offers,
            'organizer' => $organizer,
            'performer' => $performer,
            'inLanguage' => $inLanguage,
        ]), $id);
    }
}
