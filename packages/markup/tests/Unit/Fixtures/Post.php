<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Phil\Markup\MarkedUpText;

#[ORM\Entity]
#[ORM\Table(name: 'post')]
class Post
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Embedded(class: MarkedUpText::class)]
        public MarkedUpText $body,
    ) {
    }
}
