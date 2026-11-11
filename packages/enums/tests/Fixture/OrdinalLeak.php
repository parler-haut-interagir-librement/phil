<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use JsonSerializable;
use Override;

/**
 * Writes an ordinal where a value leaves the process, and once where it does not: the rule reports the first only.
 */
final readonly class OrdinalLeak implements JsonSerializable
{
    public function __construct(
        private Permission $permission,
    ) {
    }

    #[Override]
    public function jsonSerialize(): int
    {
        return $this->permission->ordinal(); // @phpstan-ignore philEnums.storedOrdinal
    }

    public function position(): int
    {
        return $this->permission->ordinal();
    }
}
