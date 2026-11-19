<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar;

use DateTimeImmutable;
use Phil\EditorialCalendar\Exception\InvalidItem;

/**
 * Items published together, as a whole: at its moment, kept in UTC, or by hand when it has none. Applying it publishes
 * every item in one go, or none; the calendar shows it as one entry.
 */
final readonly class PublicationBatch
{
    public ?DateTimeImmutable $at;

    /**
     * @param list<string> $items the identifiers of its items, in their order
     *
     * @throws InvalidItem when the identifier or the title is refused, or the items are none or repeated
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $items,
        ?DateTimeImmutable $at = null,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $id)) {
            throw InvalidItem::because(\sprintf('A batch identifier is 1 to 128 letters, digits, ".", "_", ":" or "-"; "%s" is not.', $id));
        }

        if ('' === trim($title) || mb_strlen($title) > 500) {
            throw InvalidItem::because('A batch title is 1 to 500 characters, not only spaces.');
        }

        if ([] === $items || \count(array_unique($items)) !== \count($items)) {
            throw InvalidItem::because(\sprintf('The batch "%s" holds at least one item, each once.', $id));
        }

        $this->at = $at instanceof DateTimeImmutable ? Moment::utc($at) : null;
    }

    public function isManual(): bool
    {
        return !$this->at instanceof DateTimeImmutable;
    }

    public function holds(string $item): bool
    {
        return \in_array($item, $this->items, true);
    }
}
