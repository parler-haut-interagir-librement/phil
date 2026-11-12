<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a `deletedAt` of its own, not soft deletable: the filter leaves it alone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'draft')]
class Draft
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        public ?DateTimeImmutable $deletedAt = null,
    ) {
    }
}
