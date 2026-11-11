<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * A resource that has been leased, whole or in part: the row each claim on it locks first, so that claims on one
 * resource run one at a time and a lease on the whole and a lease on a part are never both granted to two holders.
 * `claims` counts the claims that ran.
 *
 * The table is mapped for the application's schema tools and migrations; the stores of the bundle write it by plain
 * queries.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class LeasedResource
{
    public const string TABLE = 'phil_edit_lease_resource';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'resource', length: 255)]
        public readonly string $resource,
        #[ORM\Column(name: 'claims')]
        public readonly int $claims,
    ) {
    }
}
