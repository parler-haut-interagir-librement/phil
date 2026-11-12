<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Positionable;

/**
 * `Positionable`, mapped: the column `position`, null until the first flush gives it one, and one order for the table.
 * An entity ordered by groups declares `positionGroup()` itself.
 *
 * @phpstan-require-implements Positionable
 */
trait PositionableEntity
{
    #[ORM\Column(name: 'position', type: Types::INTEGER, nullable: true)]
    protected ?int $position = null;

    public function position(): ?int
    {
        return $this->position;
    }

    public function placeAt(?int $position): void
    {
        $this->position = $position;
    }

    /**
     * @return list<string>
     */
    public function positionGroup(): array
    {
        return [];
    }
}
