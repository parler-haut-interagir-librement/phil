<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Domain\Exception;

use InvalidArgumentException;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidValue::class)]
final class InvalidValueTest extends TestCase
{
    /**
     * Both types matter: `DomainError` is what the Application boundary catches, and
     * `InvalidArgumentException` is what code that does not know the domain already catches.
     */
    public function testItIsADomainErrorAndAnInvalidArgument(): void
    {
        $error = InvalidValue::because('too long');

        self::assertInstanceOf(DomainError::class, $error);
        self::assertInstanceOf(InvalidArgumentException::class, $error);
        self::assertSame('too long', $error->getMessage());
    }
}
