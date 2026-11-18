<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Fixture;

use Override;
use Phil\IncidentModeBundle\Application\Contract\CurrentMember;

/**
 * The member of the request the test chooses; a visitor while it is null.
 */
final class Member implements CurrentMember
{
    public function __construct(
        public ?string $id = null,
    ) {
    }

    #[Override]
    public function identifier(): ?string
    {
        return $this->id;
    }
}
