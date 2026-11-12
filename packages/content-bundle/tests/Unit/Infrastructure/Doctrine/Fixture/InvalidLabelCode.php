<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A refusal of the domain that is not an `InvalidArgumentException`, as an application's own may be.
 */
final class InvalidLabelCode extends DomainException implements DomainError
{
}
