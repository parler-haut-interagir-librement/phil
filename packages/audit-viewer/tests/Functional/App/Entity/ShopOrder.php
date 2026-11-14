<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\AuditBundle\Application\Contract\Auditable;
use Phil\AuditBundle\Domain\Chain\Subject;
use Stringable;

/**
 * An order of the tests, audited: its CRUD pages get the "History" action.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shop_order')]
class ShopOrder implements Auditable, Stringable
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 16)]
        public string $id = '',
        #[ORM\Column(length: 64)]
        public string $label = '',
    ) {
    }

    #[Override]
    public function auditSubject(): Subject
    {
        return Subject::of('order', $this->id);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->label;
    }
}
