<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A post whose identifier the database generates — Doctrine empties it once the row is deleted —, with a cover image.
 */
#[ORM\Entity]
#[ORM\Table(name: 'post')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 36)]
        public string $cover,
    ) {
    }
}
