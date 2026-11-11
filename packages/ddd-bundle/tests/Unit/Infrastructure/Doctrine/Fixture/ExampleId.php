<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Phil\DddBundle\Domain\Identifier\Identifier;

/**
 * The identifier the example type converts to. A test double, like the one of the `Domain` tests.
 */
final readonly class ExampleId extends Identifier
{
}
