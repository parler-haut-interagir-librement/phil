<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use Phil\IdentityProviderBundle\Domain\Encoding\Base32;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Totp\Totp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(Totp::class)]
final class TotpTest extends TestCase
{
    private const string SECRET = '12345678901234567890';

    /**
     * RFC 6238, appendix B, SHA-1, the last six digits.
     */
    #[DataProvider('vectors')]
    public function testTheVectorsOfRfc6238(int $time, string $code): void
    {
        self::assertSame($code, new Totp(self::SECRET)->at($time));
    }

    /**
     * @return iterable<array{int, string}>
     */
    public static function vectors(): iterable
    {
        yield [59, '287082'];
        yield [1_111_111_109, '081804'];
        yield [1_111_111_111, '050471'];
        yield [1_234_567_890, '005924'];
        yield [2_000_000_000, '279037'];
        yield [20_000_000_000, '353130'];
    }

    public function testACodeIsAcceptedWithinTheWindowOnly(): void
    {
        $totp = new Totp(self::SECRET);
        $time = 1_111_111_111;

        self::assertSame(37_037_037, $totp->verify('050471', $time));
        self::assertSame(37_037_036, $totp->verify($totp->at($time - 30), $time));
        self::assertSame(37_037_038, $totp->verify($totp->at($time + 30), $time));
        self::assertNull($totp->verify($totp->at($time - 60), $time));
        self::assertNull($totp->verify($totp->at($time + 60), $time));
        self::assertNull(new Totp(self::SECRET, 0)->verify($totp->at($time - 30), $time));
        self::assertSame(37_037_035, new Totp(self::SECRET, 2)->verify($totp->at($time - 60), $time));
    }

    public function testACodeIsAcceptedOnceAndNeverAnOlderOne(): void
    {
        $totp = new Totp(self::SECRET);
        $time = 1_111_111_111;

        self::assertNull($totp->verify('050471', $time, 37_037_037));
        self::assertNull($totp->verify($totp->at($time - 30), $time, 37_037_036));
        self::assertSame(37_037_037, $totp->verify('050471', $time, 37_037_036));
    }

    public function testTheCodeIsReadWithoutSpacesAndOnlyAsSixDigits(): void
    {
        $totp = new Totp(self::SECRET);

        self::assertSame(37_037_037, $totp->verify('050 471', 1_111_111_111));
        self::assertNull($totp->verify('50471', 1_111_111_111));
        self::assertNull($totp->verify('0504712', 1_111_111_111));
        self::assertNull($totp->verify("050471\n", 1_111_111_111));
        self::assertNull($totp->verify('05047a', 1_111_111_111));
    }

    #[DoesNotPerformAssertions]
    public function testTheSecretAndTheWindowAreBounded(): void
    {
        new Totp(str_repeat('a', 16), 10);
        new Totp(str_repeat('a', 16), 0);

        foreach ([[str_repeat('a', 15), 1], [str_repeat('a', 16), -1], [str_repeat('a', 16), 11]] as [$secret, $window]) {
            try {
                new Totp($secret, $window);
                self::fail('Accepted.');
            } catch (InvalidValue) {
            }
        }
    }

    public function testTheProvisioningAddress(): void
    {
        $totp = Totp::fromBase32(Base32::encode(self::SECRET));

        self::assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $totp->base32Secret());
        self::assertSame(
            'otpauth://totp/My%20Site:ada%40example.com?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=My%20Site&algorithm=SHA1&digits=6&period=30',
            $totp->provisioningUri('My Site', 'ada@example.com'),
        );
    }

    public function testAGeneratedSecretHolds160Bits(): void
    {
        self::assertSame(20, \strlen(Totp::generateSecret()));
        self::assertNotSame(Totp::generateSecret(), Totp::generateSecret());
        self::assertSame(2, new Totp(self::SECRET)->counter(60));
    }
}
