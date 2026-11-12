<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'page')]
class Page
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(length: 16)]
        public string $marking = 'draft',
    ) {
    }

    public function getMarking(): string
    {
        return $this->marking;
    }

    public function setMarking(string $marking): void
    {
        $this->marking = $marking;
    }
}
