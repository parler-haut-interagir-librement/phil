<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Domain;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\Did;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Did::class)]
#[CoversClass(InvalidValue::class)]
final class DidTest extends TestCase
{
    public function testADid(): void
    {
        $did = Did::of('did:web:example.com%3A8443:users:alice');

        self::assertSame(['did:web:example.com%3A8443:users:alice', 'web'], [$did->did, $did->method]);
        self::assertSame('plc', Did::of('did:plc:ewvi7nxzyoun6zhxrhs64oiz', 'plc')->method);
        self::assertSame('did:a1:b', Did::of('did:a1:b')->did);
        self::assertSame('did:x:a::b', Did::of('did:x:a::b')->did);
        $long = 'did:web:'.str_repeat('a', 2040);
        self::assertSame($long, Did::of($long)->did);
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function badDids(): iterable
    {
        yield 'no prefix' => ['web:example.com', null, '"web:example.com" is not a DID: "did:", a method, ":" and an identifier.'];
        yield 'a method in capitals' => ['did:WEB:example.com', null, 'is not a DID'];
        yield 'no identifier' => ['did:web:', null, 'is not a DID'];
        yield 'a trailing colon' => ['did:web:example.com:', null, 'is not a DID'];
        yield 'a space' => ['did:web:exa mple.com', null, 'is not a DID'];
        yield 'a bad escape' => ['did:web:a%2', null, 'is not a DID'];
        yield 'a new line' => ["did:web:a\n", null, 'is not a DID'];
        yield 'a leading space' => [' did:web:a', null, 'is not a DID'];
        yield 'too long' => ['did:web:'.str_repeat('a', 2041), null, 'is not a DID'];
        yield 'another method' => ['did:plc:abc', 'web', '"did:plc:abc" is not a did:web identifier.'];
    }

    #[DataProvider('badDids')]
    public function testABadDidIsRefused(string $did, ?string $method, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Did::of($did, $method);
    }
}
