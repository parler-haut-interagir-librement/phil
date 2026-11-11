<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'image')]
class Image
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }
}
