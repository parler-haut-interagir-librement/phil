<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture;

use Phil\DddBundle\Domain\Identifier\Identifier;

/**
 * `Identifier` is abstract; exercising it takes a concrete subclass. This one is a test double and belongs to
 * no context: putting it in `src/` would make it an identifier someone would end up using.
 */
final readonly class ExampleId extends Identifier
{
}
