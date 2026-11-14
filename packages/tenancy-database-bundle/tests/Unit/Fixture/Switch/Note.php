<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Switch;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'note')]
final class Note
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 1;
}
