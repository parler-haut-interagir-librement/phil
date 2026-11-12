<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Deactivatable;

/**
 * Deactivatable by hand, with no `active` column: the filter leaves it visible.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fan')]
class Fan implements Deactivatable
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }

    public function isActive(): bool
    {
        return true;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return null;
    }

    public function deactivate(): void
    {
    }

    public function reactivate(): void
    {
    }

    public function stampDeactivated(?DateTimeImmutable $at): void
    {
    }
}
