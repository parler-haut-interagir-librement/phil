<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Did;

use Phil\OpenBadges\Did\DidWeb;
use Phil\OpenBadges\Exception\InvalidCredential;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DidWeb::class)]
final class DidWebTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function addresses(): iterable
    {
        yield 'a domain' => ['did:web:w3c-ccg.github.io', 'https://w3c-ccg.github.io/.well-known/did.json'];
        yield 'a path' => ['did:web:w3c-ccg.github.io:user:alice', 'https://w3c-ccg.github.io/user/alice/did.json'];
        yield 'a port' => ['did:web:example.com%3A3000:user:alice', 'https://example.com:3000/user/alice/did.json'];
        yield 'a port in lowercase' => ['did:web:example.com%3a8443', 'https://example.com:8443/.well-known/did.json'];
        yield 'the default port' => ['did:web:example.com%3A443', 'https://example.com/.well-known/did.json'];
        yield 'a host in capitals' => ['did:web:Example.COM', 'https://example.com/.well-known/did.json'];
        yield 'an escaped segment' => ['did:web:example.com:issuers:caf%C3%A9_1~a', 'https://example.com/issuers/caf%C3%A9_1~a/did.json'];
    }

    #[DataProvider('addresses')]
    public function testTheDocumentOfADidWebIsAtItsHttpsAddress(string $did, string $url): void
    {
        $didWeb = DidWeb::fromString($did);

        self::assertSame($did, $didWeb->did);
        self::assertSame($url, $didWeb->url->toString());
        self::assertTrue(DidWeb::isDidWeb($did));
    }

    public function testAHostTheAddressWouldWriteOtherwiseIsRefused(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('"did:web:example.com." names no host a did:web resolves.'));
        DidWeb::fromString('did:web:example.com.');
    }

    public function testAnIdentifierOfTheLongestLengthIsReadAsAnAddressNotRefusedAsSyntax(): void
    {
        $did = 'did:web:example.com:'.str_repeat('a', 2048 - \strlen('did:web:example.com:'));
        self::assertSame(2048, \strlen($did));

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageMatches('/^"did:web:example\.com:a+" names no host a did:web resolves: /');
        DidWeb::fromString($did);
    }

    public function testOtherMethodsAreNotDidWeb(): void
    {
        self::assertFalse(DidWeb::isDidWeb('did:key:z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2'));
        self::assertFalse(DidWeb::isDidWeb('https://example.com/issuer'));
        self::assertFalse(DidWeb::isDidWeb('did:webs:example.com'));
        self::assertSame('did:web:', DidWeb::PREFIX);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        $syntax = static fn (string $did): string => \sprintf('"%s" is not a did:web identifier.', $did);
        $segment = static fn (string $did): string => \sprintf('The path of "%s" holds a segment a did:web does not.', $did);

        yield 'another method' => ['did:key:z6Mk', $syntax('did:key:z6Mk')];
        yield 'no host' => ['did:web:', $syntax('did:web:')];
        yield 'a fragment' => ['did:web:example.com#key-1', $syntax('did:web:example.com#key-1')];
        yield 'a slash' => ['did:web:example.com/issuer', $syntax('did:web:example.com/issuer')];
        yield 'an empty segment' => ['did:web:example.com::a', $syntax('did:web:example.com::a')];
        yield 'a trailing colon' => ['did:web:example.com:', $syntax('did:web:example.com:')];
        yield 'a port of zero' => ['did:web:example.com%3A0', $syntax('did:web:example.com%3A0')];
        yield 'a port too long' => ['did:web:example.com%3A123456', $syntax('did:web:example.com%3A123456')];
        yield 'an escape in the host' => ['did:web:exa%6Dple.com', $syntax('did:web:exa%6Dple.com')];
        yield 'a broken escape' => ['did:web:example.com:a%2', $syntax('did:web:example.com:a%2')];
        yield 'a space' => ['did:web:example.com:a b', $syntax('did:web:example.com:a b')];
        yield 'a new line' => ["did:web:example.com\n", $syntax("did:web:example.com\n")];
        yield 'too long' => ['did:web:example.com:'.str_repeat('a', 2048), $syntax('did:web:example.com:'.str_repeat('a', 2048))];
        yield 'a text before the prefix' => ['xdid:web:example.com', $syntax('xdid:web:example.com')];
        yield 'a dot segment' => ['did:web:example.com:.', $segment('did:web:example.com:.')];
        yield 'a dot dot segment' => ['did:web:example.com:a:..', $segment('did:web:example.com:a:..')];
        yield 'an escaped dot dot' => ['did:web:example.com:%2E%2e', $segment('did:web:example.com:%2E%2e')];
        yield 'an escaped slash' => ['did:web:example.com:a%2Fb', $segment('did:web:example.com:a%2Fb')];
        yield 'an escaped backslash' => ['did:web:example.com:a%5Cb', $segment('did:web:example.com:a%5Cb')];
        yield 'an escaped question mark' => ['did:web:example.com:a%3Fb', $segment('did:web:example.com:a%3Fb')];
        yield 'an escaped hash' => ['did:web:example.com:a%23b', $segment('did:web:example.com:a%23b')];
        yield 'an escaped null' => ['did:web:example.com:a%00b', $segment('did:web:example.com:a%00b')];
    }

    #[DataProvider('refused')]
    public function testAnIdentifierThatIsNotADidWebIsRefused(string $did, string $reason): void
    {
        $this->expectExceptionObject(InvalidCredential::because($reason));
        DidWeb::fromString($did);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hosts(): iterable
    {
        yield 'an IP address' => ['did:web:93.184.215.14'];
        yield 'localhost' => ['did:web:localhost%3A8000'];
        yield 'a single name' => ['did:web:intranet'];
        yield 'a port out of range' => ['did:web:example.com%3A70000'];
    }

    #[DataProvider('hosts')]
    public function testAHostThatIsNotAPublicNameIsRefused(string $did): void
    {
        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageMatches('/^"'.preg_quote($did, '/').'" names no host a did:web resolves/');
        DidWeb::fromString($did);
    }
}
