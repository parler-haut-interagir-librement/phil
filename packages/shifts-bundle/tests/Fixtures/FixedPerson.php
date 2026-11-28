<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Fixtures;

use Override;
use Phil\ShiftsBundle\Application\Contract\CurrentPerson;

/**
 * A person signed in, or nobody, with the roles a test gives.
 */
final readonly class FixedPerson implements CurrentPerson
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private ?string $person,
        private array $roles = [],
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->person;
    }

    #[Override]
    public function roles(): array
    {
        return $this->roles;
    }
}
