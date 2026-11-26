<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture;

use Phil\DddBundle\Domain\Identifier\Identifier;

/**
 * A subclass of `Identifier` that is still abstract: it names no class to build, so the integrations must leave it
 * alone.
 */
abstract readonly class AbstractExampleId extends Identifier
{
}
