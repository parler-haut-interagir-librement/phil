<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Pipeline;

use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * Every data mapper over the two rows of one dimension: what an editing command or form calls with the
 * submitted values.
 *
 * The rows are the stored draft rows, unlocalized and localized, of the dimension being edited; the caller
 * loads or creates them, and persists them afterwards. The order of the mappers does not matter, since no
 * two write the same values. A value one mapper refuses stops the whole write before the caller persists
 * anything.
 */
final readonly class ContentDataMapper
{
    /**
     * @var array<DataMapper>
     */
    private array $mappers;

    /**
     * @param iterable<DataMapper> $mappers read once, here: a generator cannot be walked for a second write
     */
    public function __construct(iterable $mappers)
    {
        $this->mappers = [...$mappers];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidContent|DomainError when a submitted value is refused
     */
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        foreach ($this->mappers as $mapper) {
            $mapper->map($unlocalized, $localized, $data);
        }
    }
}
