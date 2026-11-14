<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Fixtures;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity for the Doctrine data source.
 */
#[ORM\Entity]
#[ORM\Table(name: 'paper')]
class Paper
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        public int $id,
        #[ORM\Column]
        public string $title,
        #[ORM\Column]
        public string $status,
        #[ORM\Column]
        public bool $live,
        #[ORM\Column]
        public DateTimeImmutable $at,
    ) {
    }
}
