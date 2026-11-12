<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'page')]
class Page
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(length: 255)]
        public string $title,
        #[ORM\Column(length: 8)]
        public string $locale = 'fr',
    ) {
    }
}
