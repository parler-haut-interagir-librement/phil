<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Internal;

use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Internal\RsaKeySize;
use Phil\MailAuthCheck\Tests\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RsaKeySize::class)]
#[CoversClass(InvalidRecord::class)]
final class RsaKeySizeTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function sizes(): iterable
    {
        foreach ([8, 9, 15, 16, 512, 1023, 1024, 1025, 2047, 2048, 4096] as $bits) {
            yield $bits.' bits' => [$bits];
        }
    }

    #[DataProvider('sizes')]
    public function testTheModulusIsMeasuredToTheBit(int $bits): void
    {
        self::assertSame($bits, RsaKeySize::bits(Keys::rsaSpki($bits)));
        self::assertSame($bits, RsaKeySize::bits(Keys::rsaPublicKey($bits)));
    }

    public function testLeadingZeroBytesOfTheModulusAreNotCounted(): void
    {
        $key = Keys::der(0x30, Keys::der(0x02, "\x00\x00\x01\xFF").Keys::der(0x02, "\x03"));

        self::assertSame(9, RsaKeySize::bits($key));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidKeys(): iterable
    {
        $rsaOid = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00";
        $rsaKey = Keys::rsaPublicKey(1024);

        yield 'empty' => ['', 'its DER structure is not a public key'];
        yield 'one byte' => ["\x30", 'its DER structure is not a public key'];
        yield 'not a sequence' => ["\x31\x00", 'its DER structure is not a public key'];
        yield 'an empty sequence' => ["\x30\x00", 'its DER structure is not a public key'];
        yield 'cut short' => ["\x30\x05\x02\x01", 'its DER structure is cut short'];
        yield 'an indefinite length' => ["\x30\x80\x02\x01\x01\x00\x00", 'its DER lengths are out of range'];
        yield 'a length of three bytes' => ["\x30\x83\x00\x00\x03\x02\x01\x01", 'its DER lengths are out of range'];
        yield 'a long length cut' => ["\x30\x82\x01", 'its DER lengths are out of range'];
        yield 'another algorithm' => [Keys::der(0x30, Keys::der(0x30, "\x06\x03\x2B\x65\x70").Keys::der(0x03, "\x00".$rsaKey)), 'it is not an RSA key'];
        yield 'bits not whole bytes' => [Keys::der(0x30, Keys::der(0x30, $rsaOid).Keys::der(0x03, "\x01".$rsaKey)), 'its key bits are not whole bytes'];
        yield 'no bit string' => [Keys::der(0x30, Keys::der(0x30, $rsaOid).Keys::der(0x04, "\x00".$rsaKey)), 'its DER structure is not a public key'];
        yield 'an empty modulus at the end' => ["\x30\x02\x02\x00", 'its modulus is zero'];
        yield 'a long length that ends the key' => ["\x30\x81\x05", 'its DER structure is cut short'];
        yield 'a zero modulus' => [Keys::der(0x30, Keys::der(0x02, "\x00\x00").Keys::der(0x02, "\x03")), 'its modulus is zero'];
    }

    #[DataProvider('invalidKeys')]
    public function testAnInvalidKeyIsRefused(string $der, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('the public key cannot be read: '.$message.'.');

        RsaKeySize::bits($der);
    }

    public function testALengthOfOneOrTwoBytesIsRead(): void
    {
        $modulus = "\x00\x80".str_repeat("\x01", 199);
        $key = "\x30\x81".pack('C', \strlen($modulus) + 3 + 3)."\x02\x81".pack('C', \strlen($modulus)).$modulus."\x02\x01\x03";

        self::assertSame(1600, RsaKeySize::bits($key));
        self::assertSame(4096, RsaKeySize::bits(Keys::rsaPublicKey(4096)));
    }
}
