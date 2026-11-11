<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture;

use Phil\DddBundle\Domain\Identifier\Identifier;

/**
 * A second subclass, only to check that `equals()` refuses to compare two identifiers of different types.
 * Without this case, nothing stops a `UserId` from being "equal" to a `SessionId` carrying the same UUID.
 */
final readonly class OtherId extends Identifier
{
}
