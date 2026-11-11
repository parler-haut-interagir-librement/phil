<?php

declare(strict_types=1);

namespace Phil\Fonts\Name;

/**
 * The strings of a face's `name` table, and the one to show for each name.
 */
final readonly class Names
{
    /**
     * @param list<NameRecord> $records
     */
    public function __construct(
        public array $records = [],
    ) {
    }

    /**
     * A name in the language a reader is most likely to expect: Windows US English first, then any other Windows
     * language, then Unicode, then Macintosh; an empty string counts as no name.
     */
    public function get(NameId $id): ?string
    {
        $records = array_filter($this->all($id), static fn (NameRecord $record): bool => '' !== $record->value);
        $preferences = [
            static fn (NameRecord $record): bool => 3 === $record->platform && 0x0409 === $record->language,
            static fn (NameRecord $record): bool => 3 === $record->platform,
            static fn (NameRecord $record): bool => 0 === $record->platform,
            static fn (NameRecord $record): bool => true,
        ];

        foreach ($preferences as $preferred) {
            $record = array_find($records, $preferred);

            if (null !== $record) {
                return $record->value;
            }
        }

        return null;
    }

    /**
     * Every string of a name, in each language and on each platform the font wrote it for.
     *
     * @return list<NameRecord>
     */
    public function all(NameId $id): array
    {
        return array_values(array_filter($this->records, static fn (NameRecord $record): bool => $id->value === $record->nameId));
    }
}
