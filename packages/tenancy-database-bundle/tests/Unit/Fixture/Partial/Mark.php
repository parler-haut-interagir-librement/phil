<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Partial;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mark')]
final class Mark
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 0;
}
