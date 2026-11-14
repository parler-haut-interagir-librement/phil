<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration\App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Reference data every database holds on its own: not part of a tenant, never copied by a move.
 */
#[ORM\Entity]
#[ORM\Table(name: 'currency')]
class Currency
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 3)]
        public string $code,
    ) {
    }
}
