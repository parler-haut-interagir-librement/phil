<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * An entity whose identifier the database generates: Doctrine empties it once the row is deleted.
 */
#[ORM\Entity]
#[ORM\Table(name: 'note')]
class Note
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        public string $text,
    ) {
    }
}
