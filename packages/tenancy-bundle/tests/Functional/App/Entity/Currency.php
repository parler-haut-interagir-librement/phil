<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Reference data shared by every tenant: no attribute, so the filter leaves it alone.
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
