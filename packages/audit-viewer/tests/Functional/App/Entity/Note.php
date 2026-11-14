<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Stringable;

/**
 * A note of the tests, not audited: its CRUD pages get no "History" action.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shop_note')]
class Note implements Stringable
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 16)]
        public string $id = '',
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->id;
    }
}
