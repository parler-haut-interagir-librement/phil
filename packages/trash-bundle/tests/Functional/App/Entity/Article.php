<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'article')]
class Article
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(length: 255)]
        public string $title,
        #[ORM\Column(length: 36, nullable: true)]
        public ?string $section = null,
    ) {
    }
}
