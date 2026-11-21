<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use ErrorException;
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

    public function testAnEcdsaSignatureIsReadAsItsTwoNumbers(): void
    {
        $r = "\x01".str_repeat("\x02", 31);
        $s = "\x80".str_repeat("\x03", 31);
        $der = "\x30\x45\x02\x20".$r."\x02\x21\x00".$s;
        self::assertSame($r.$s, Der::ecdsaSignature($der, 32));

        $short = "\x30\x08\x02\x02\x00\x7F\x02\x02\x01\x02";
        self::assertSame(str_repeat("\x00", 31)."\x7F".str_repeat("\x00", 30)."\x01\x02", Der::ecdsaSignature($short, 32));
        self::assertSame("\x00\x7F\x01\x02", Der::ecdsaSignature($short, 2));

        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);

        for ($i = 0; $i < 30; ++$i) {
            openssl_sign('input '.$i, $signature, $key, \OPENSSL_ALGO_SHA256);
            self::assertIsString($signature);
            self::assertSame(64, \strlen(Der::ecdsaSignature($signature, 32)));
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function brokenSignatures(): iterable
    {
        yield 'too short' => ["\x30\x05\x02\x01\x01\x02\x01", 'sequence'];
        yield 'not a sequence' => ["\x31\x06\x02\x01\x01\x02\x01\x01", 'sequence'];
        yield 'wrong length' => ["\x30\x07\x02\x01\x01\x02\x01\x01", 'sequence'];
        yield 'long length' => ["\x30\x06\x02\x01\x01\x02\x01\x01\x00", 'sequence'];
        yield 'first not an integer' => ["\x30\x06\x03\x01\x01\x02\x01\x01", 'Integer 1'];
        yield 'second not an integer' => ["\x30\x06\x02\x01\x01\x04\x01\x01", 'Integer 2'];
        yield 'empty integer' => ["\x30\x06\x02\x00\x02\x02\x01\x01", 'Integer 1'];
        yield 'integer past the end' => ["\x30\x06\x02\x01\x01\x02\x03\x01", 'Integer 2'];
        yield 'number too long' => ["\x30\x08\x02\x03\x01\x02\x03\x02\x01\x01", 'Integer 1'];
        yield 'long form length' => ["\x30\x06\x02\x81\x01\x02\x01\x01", 'Integer 1'];
        yield 'integer one byte past the end' => ["\x30\x06\x02\x01\x01\x02\x02\x01", 'Integer 2'];
        yield 'bytes left over' => ["\x30\x08\x02\x01\x01\x02\x01\x01\x05\x00", 'after its two integers'];
    }

    #[DataProvider('brokenSignatures')]
    public function testABrokenEcdsaSignatureIsRefused(string $der, string $message): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        Der::ecdsaSignature($der, 2);
    }

    public function testAnIntegerMayHold127BytesAndNothingIsReadPastTheEnd(): void
    {
        $content = "\x02\x7F".str_repeat("\x01", 127)."\x02\x01\x01"; // 132 bytes, the 0x84 of the sequence

        self::assertSame(str_repeat("\x01", 127).str_repeat("\x00", 126)."\x01", Der::ecdsaSignature("\x30\x84".$content, 127));

        // A warning of PHP fails the test: a header that ends the signature is refused before its length is read.
        set_error_handler(static fn (int $level, string $message): never => throw new ErrorException($message, 0, $level));

        try {
            foreach (["\x30\x06\x02\x03\x01\x02\x03\x02" => 'Integer 2', "\x30\x06\x02\x04\x01\x02\x03\x04" => 'Integer 2'] as $der => $message) {
                try {
                    Der::ecdsaSignature($der, 32);
                    self::fail($message);
                } catch (MalformedInput $malformed) {
                    self::assertStringContainsString($message, $malformed->getMessage());
                }
            }
        } finally {
            restore_error_handler();
        }
    }

    public function testACertificateIsWrittenAsPem(): void
    {
        self::assertSame("-----BEGIN CERTIFICATE-----\nAQID\n-----END CERTIFICATE-----\n", Der::certificate("\x01\x02\x03"));
    }
}
