<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;

/**
 * A root whose identifier the database generates, an integer.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_ledger')]
class Ledger extends AbstractContentRoot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    protected ?int $id = null;

    public function id(): ?int
    {
        return $this->id;
    }

    #[Override]
    public static function dimensionContentClass(): string
    {
        return LedgerDimension::class;
    }
}
