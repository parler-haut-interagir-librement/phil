<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit\Domain;

use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Domain\ValueObject\SigningKey;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Origin::class)]
#[CoversClass(SigningKey::class)]
#[CoversClass(InvalidEmbed::class)]
final class OriginAndKeyTest extends TestCase
{
    public function testAnOriginIsNormalisedByPhilUrl(): void
    {
        $origin = Origin::fromString('HTTPS://Partner.Example:443');

        self::assertSame(['https', 'partner.example', null], [$origin->scheme, $origin->host, $origin->port]);
        self::assertSame('https://partner.example', $origin->toString());
        self::assertSame('http://localhost:8000', Origin::fromString('http://localhost:8000', true)->toString());
        self::assertSame('https://xn--bcher-kva.example', Origin::fromString('https://bücher.example')->toString());
        self::assertTrue($origin->equals(Origin::fromString('https://partner.example')));
        self::assertFalse($origin->equals(Origin::fromString('https://partner.example:8443')));
        self::assertSame('https://a.example:8443', Origin::of(Url::fromString('https://a.example:8443/page?x#y'))->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notOrigins(): iterable
    {
        yield 'a path' => ['https://partner.example/page'];
        yield 'a trailing slash' => ['https://partner.example/'];
        yield 'a query' => ['https://partner.example?x=1'];
        yield 'a fragment' => ['https://partner.example#x'];
        yield 'credentials' => ['https://user@partner.example'];
        yield 'an IP address' => ['https://127.0.0.1'];
        yield 'localhost' => ['http://localhost'];
        yield 'no scheme' => ['partner.example'];
        yield 'another scheme' => ['ftp://partner.example'];
        yield 'a trailing space after the slash' => ['https://partner.example/ '];
        yield 'nothing' => [''];
        yield 'a wildcard' => ['*'];
    }

    #[DataProvider('notOrigins')]
    public function testWhatIsNotAnOrigin(string $text): void
    {
        $this->expectException(InvalidEmbed::class);

        Origin::fromString($text);
    }

    public function testTheReasons(): void
    {
        try {
            Origin::fromString('https://a.example/x');
            self::fail('Refused.');
        } catch (InvalidEmbed $invalidEmbed) {
            self::assertSame('"https://a.example/x" is not an origin: an origin has no path, query nor fragment.', $invalidEmbed->getMessage());
        }

        try {
            Origin::fromString('nothing');
            self::fail('Refused.');
        } catch (InvalidEmbed $invalidEmbed) {
            self::assertStringStartsWith('"nothing" is not an origin: ', $invalidEmbed->getMessage());
        }
    }

    public function testAKey(): void
    {
        $key = SigningKey::of('2026-11_a', str_repeat('s', 32));

        self::assertSame(['2026-11_a', str_repeat('s', 32)], [$key->id, $key->secret]);
        self::assertSame(32, \strlen(SigningKey::of(str_repeat('k', 32), str_repeat('s', 64))->id));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedKeys(): iterable
    {
        yield 'empty identifier' => ['', str_repeat('s', 32), 'The identifier of a key, "", is 1 to 32 letters, digits, "_" or "-".'];
        yield 'long identifier' => [str_repeat('k', 33), str_repeat('s', 32), \sprintf('The identifier of a key, "%s", is 1 to 32 letters, digits, "_" or "-".', str_repeat('k', 33))];
        yield 'a dot' => ['a.b', str_repeat('s', 32), 'The identifier of a key, "a.b", is 1 to 32 letters, digits, "_" or "-".'];
        yield 'a new line' => ["a\n", str_repeat('s', 32), "The identifier of a key, \"a\n\", is 1 to 32 letters, digits, \"_\" or \"-\"."];
        yield 'short secret' => ['k', str_repeat('s', 31), 'The secret of the key "k" holds 32 bytes at least.'];
    }

    #[DataProvider('refusedKeys')]
    public function testARefusedKey(string $id, string $secret, string $message): void
    {
        $this->expectExceptionObject(InvalidEmbed::because($message));

        SigningKey::of($id, $secret);
    }
}
