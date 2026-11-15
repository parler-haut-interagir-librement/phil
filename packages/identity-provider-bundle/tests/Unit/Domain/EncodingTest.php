<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use OpenSSLAsymmetricKey;
use Phil\IdentityProviderBundle\Domain\Encoding\Base32;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Encoding\Der;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Base64Url::class)]
#[CoversClass(Base32::class)]
#[CoversClass(Der::class)]
#[CoversClass(MalformedInput::class)]
final class EncodingTest extends TestCase
{
    public function testBase64UrlWithoutPadding(): void
    {
        self::assertSame('', Base64Url::encode(''));
        self::assertSame('', Base64Url::decode(''));
        self::assertSame('-_8', Base64Url::encode("\xFB\xFF"));
        self::assertSame("\xFB\xFF", Base64Url::decode('-_8'));
        self::assertSame('YQ', Base64Url::encode('a'));
    }

    #[DataProvider('notBase64Url')]
    public function testBase64UrlRefusesWhatItCannotHold(string $text): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains('The text is not base64url without padding.');

        Base64Url::decode($text);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notBase64Url(): iterable
    {
        yield 'padding' => ['YQ=='];
        yield 'standard alphabet' => ['+/8'];
        yield 'impossible length' => ['A'];
    }

    /**
     * RFC 4648, section 10.
     */
    #[DataProvider('base32Vectors')]
    public function testBase32Vectors(string $bytes, string $text): void
    {
        self::assertSame($text, Base32::encode($bytes));
        self::assertSame($bytes, Base32::decode($text));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function base32Vectors(): iterable
    {
        yield ['', ''];
        yield ['f', 'MY'];
        yield ['fo', 'MZXQ'];
        yield ['foo', 'MZXW6'];
        yield ['foob', 'MZXW6YQ'];
        yield ['fooba', 'MZXW6YTB'];
        yield ['foobar', 'MZXW6YTBOI'];
    }

    public function testBase32ReadsWhatPeopleType(): void
    {
        self::assertSame('foobar', Base32::decode('mzxw 6ytb oi======'));
        self::assertSame("\xFF", Base32::decode('74'));
    }

    public function testBase32RefusesOtherCharacters(): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains('The text is not base32.');

        Base32::decode('MZ1');
    }

    public function testDerLengthsOfOneAndTwoBytes(): void
    {
        $der = static fn (int $bytes): string => (string) base64_decode(implode('', \array_slice(explode("\n", trim(Der::rsaPublicKey(str_repeat("\x01", $bytes), "\x01\x00\x01"))), 1, -1)), true);

        self::assertStringContainsString("\x02\x7F".str_repeat("\x01", 127)."\x02\x03", $der(127));
        self::assertStringContainsString("\x02\x81\x80".str_repeat("\x01", 128)."\x02\x03", $der(128));
        self::assertStringStartsWith("\x30\x81\x9E", $der(128));
        self::assertStringStartsWith("\x30\x82\x01\x21", $der(256));
    }

    public function testP256CoordinatesHold32Bytes(): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains('A P-256 coordinate holds 32 bytes.');

        Der::p256PublicKey(str_repeat("\x01", 31), str_repeat("\x01", 32));
    }

    public function testPublicKeysAreReadByOpenSsl(): void
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsArray($details['rsa']);
        self::assertIsString($details['rsa']['n']);
        self::assertIsString($details['rsa']['e']);
        $pem = Der::rsaPublicKey($details['rsa']['n'], $details['rsa']['e']);

        self::assertStringStartsWith("-----BEGIN PUBLIC KEY-----\n", $pem);
        self::assertStringEndsWith("\n-----END PUBLIC KEY-----\n", $pem);
        self::assertSame($details['key'], $pem);
    }
}
