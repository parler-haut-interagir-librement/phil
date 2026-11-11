<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Messenger\Fixture;

use Phil\DddBundle\Domain\Exception\DomainError;
use RuntimeException;

final class ExampleDomainError extends RuntimeException implements DomainError
{
}
