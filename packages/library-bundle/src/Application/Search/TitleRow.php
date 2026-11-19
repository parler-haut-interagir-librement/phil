<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Search;

use Phil\LibraryBundle\Domain\Status\TitleKind;

/**
 * A title as a line of the search: what the list shows, and how many of its copies there are and are on the shelf.
 */
final readonly class TitleRow
{
    public function __construct(
        public string $id,
        public TitleKind $kind,
        public string $name,
        public ?string $creators,
        public ?int $year,
        public ?int $minPlayers,
        public ?int $maxPlayers,
        public ?int $minMinutes,
        public ?int $maxMinutes,
        public ?int $minAge,
        public int $copies,
        public int $available,
    ) {
    }
}
