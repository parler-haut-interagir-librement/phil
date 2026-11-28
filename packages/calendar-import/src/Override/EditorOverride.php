<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Override;

use Phil\CalendarImport\Exception\InvalidSource;
use Phil\CalendarImport\Field;

/**
 * What an editor changed of an imported event, by its key in its source: the fields replaced — a null field is the
 * source's —, and whether the event is hidden. An override is kept apart from the events read, so that each read
 * applies it again: it survives every refresh, until the editor takes it away.
 */
final readonly class EditorOverride
{
    /**
     * @param ?list<string> $tags the tags in place of the source's categories, or null for the source's
     */
    public function __construct(
        public string $key,
        public ?string $title = null,
        public ?string $location = null,
        public ?string $description = null,
        public ?array $tags = null,
        public bool $hidden = false,
    ) {
        if ('' === $key) {
            throw InvalidSource::because('An override names the key of the event it changes.');
        }
    }

    public function replaces(Field $field): bool
    {
        return null !== match ($field) {
            Field::Title => $this->title,
            Field::Location => $this->location,
            Field::Description => $this->description,
            Field::Tags => $this->tags,
        };
    }

    /**
     * The fields this override replaces.
     *
     * @return list<Field>
     */
    public function fields(): array
    {
        return array_values(array_filter(Field::cases(), $this->replaces(...)));
    }
}
