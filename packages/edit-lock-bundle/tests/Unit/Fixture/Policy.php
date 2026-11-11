<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Fixture;

use Override;
use Phil\EditLockBundle\Application\EditPolicy;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;

/**
 * Editors are the holders listed; owners, who may take over, too.
 */
final readonly class Policy implements EditPolicy
{
    /**
     * @param list<string> $editors
     * @param list<string> $owners
     */
    public function __construct(
        private array $editors = ['ada', 'bob', 'cy'],
        private array $owners = ['ada'],
    ) {
    }

    #[Override]
    public function mayEdit(Holder $holder, string $resource): bool
    {
        return \in_array($holder->id, $this->editors, true) && 'secret' !== $resource;
    }

    #[Override]
    public function mayTakeOver(Holder $holder, Lease $lease): bool
    {
        return \in_array($holder->id, $this->owners, true);
    }
}
