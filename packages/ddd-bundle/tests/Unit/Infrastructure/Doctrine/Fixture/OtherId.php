<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Phil\DddBundle\Domain\Identifier\Identifier;

/**
 * Another identifier class, only to check that a column refuses a value of the wrong class.
 */
final readonly class OtherId extends Identifier
{
}
