<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Encoding;

use Phil\OpenBadges\Encoding\Base58Btc;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Encoding\Multibase;
use Phil\OpenBadges\Exception\InvalidCredential;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Base64Url::class)]
#[CoversClass(Base58Btc::class)]
#[CoversClass(Multibase::class)]
final class EncodingTest extends TestCase
{
    public function testBase64Url(): void
    {
        self::assertSame('-_8', Base64Url::encode("\xfb\xff"));
        self::assertSame("\xfb\xff", Base64Url::decode('-_8'));
        self::assertSame('', Base64Url::decode(''));
        $this->expectExceptionObject(InvalidCredential::because('A part of the token is not URL-safe base 64.'));
        Base64Url::decode('+/8=');
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function base58(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'one zero' => ["\0", '1'];
        yield 'leading zeros' => ["\0\0\x01", '112'];
        yield 'a byte' => ["\xff", '5Q'];
        yield 'a text' => ['Hello World!', '2NEpo7TZRRrLZSi2U'];
        yield 'two bytes' => ["\x01\x00", '5R'];
        yield 'the quick fox' => ['The quick brown fox jumps over the lazy dog.', 'USm3fpXnKG5EUBx2ndxBDMPVciP5hGey2Jh4NDv6gmeo1LkMeiKrLJUUBk6Z'];
    }

    #[DataProvider('base58')]
    public function testBase58(string $bytes, string $text): void
    {
        self::assertSame($text, Base58Btc::encode($bytes));
        self::assertSame($bytes, Base58Btc::decode($text));
    }

    public function testBase58RoundTripsRandomBytes(): void
    {
        for ($length = 1; $length < 70; ++$length) {
            $bytes = random_bytes($length);
            self::assertSame($bytes, Base58Btc::decode(Base58Btc::encode($bytes)));
        }
    }

    public function testBase58RefusesAnotherAlphabet(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('A base 58 text has at most 512 characters of the Bitcoin alphabet.'));
        Base58Btc::decode('0OIl');
    }

    public function testBase58RefusesALongText(): void
    {
        self::assertSame(512, \strlen(Base58Btc::encode(Base58Btc::decode(str_repeat('2', 512)))));
        $this->expectException(InvalidCredential::class);
        Base58Btc::decode(str_repeat('2', 513));
    }

    public function testMultibase(): void
    {
        self::assertSame('z5Q', Multibase::base58("\xff"));
        self::assertSame('u-_8', Multibase::base64Url("\xfb\xff"));
        self::assertSame("\xff", Multibase::decode('z5Q'));
        self::assertSame("\xfb\xff", Multibase::decode('u-_8'));

        foreach (['', 'm+/8=', 'Z5Q'] as $text) {
            try {
                Multibase::decode($text);
                self::fail(\sprintf('"%s" was expected to be refused.', $text));
            } catch (InvalidCredential $refused) {
                self::assertSame('A multibase text starts with "z" (base 58) or "u" (base 64 URL).', $refused->getMessage());
            }
        }
    }
}
