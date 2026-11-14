<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Domain\ValueObject;

use LogicException;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyDatabaseBundle\Domain\ValueObject\DatabaseCredentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseCredentials::class)]
final class DatabaseCredentialsTest extends TestCase
{
    public function testTheCredentialsKeepTheirValues(): void
    {
        $credentials = new DatabaseCredentials('tenant', 's3cret', 'db.example', 5432);

        self::assertSame('tenant', $credentials->user);
        self::assertSame('s3cret', $credentials->password);
        self::assertSame('db.example', $credentials->host);
        self::assertSame(5432, $credentials->port);
    }

    public function testTheHostAndThePortAreOptional(): void
    {
        $credentials = new DatabaseCredentials('tenant', '');

        self::assertNull($credentials->host);
        self::assertNull($credentials->port);
    }

    public function testADumpMasksThePassword(): void
    {
        self::assertSame(['user' => 'tenant', 'password' => '***', 'host' => null, 'port' => 1], new DatabaseCredentials('tenant', 's3cret', port: 1)->__debugInfo());
        self::assertStringNotContainsString('s3cret', print_r(new DatabaseCredentials('tenant', 's3cret'), true));
    }

    public function testTheCredentialsAreNeverSerialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Database credentials are never serialized; seal them with CredentialSeal to store them.');

        serialize(new DatabaseCredentials('tenant', 's3cret'));
    }

    public function testThePortsAtTheBoundsAreKept(): void
    {
        self::assertSame(DatabaseCredentials::MAX_PORT, new DatabaseCredentials('u', 'p', port: 65535)->port);
        self::assertSame(1, new DatabaseCredentials('u', 'p', port: 1)->port);
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?int, string}>
     */
    public static function refused(): iterable
    {
        yield 'empty user' => ['', 'p', null, null, 'A database user cannot be empty.'];
        yield 'empty host' => ['u', 'p', '', null, 'A database host is null or a host name, never empty.'];
        yield 'control character in the user' => ["u\n", 'p', null, null, 'A database user, password or host cannot contain control characters.'];
        yield 'control character in the password' => ['u', "p\x00", null, null, 'A database user, password or host cannot contain control characters.'];
        yield 'delete in the host' => ['u', 'p', "h\x7F", null, 'A database user, password or host cannot contain control characters.'];
        yield 'port zero' => ['u', 'p', null, 0, 'A database port is between 1 and 65535; this one is 0.'];
        yield 'port too large' => ['u', 'p', null, 65536, 'A database port is between 1 and 65535; this one is 65536.'];
    }

    #[DataProvider('refused')]
    public function testAnInvalidValueIsRefused(string $user, string $password, ?string $host, ?int $port, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DatabaseCredentials($user, $password, $host, $port);
    }
}
