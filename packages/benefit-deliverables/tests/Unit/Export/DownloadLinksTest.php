<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Unit\Export;

use DateTimeImmutable;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use Phil\BenefitDeliverables\Export\DownloadLinks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DownloadLinks::class)]
final class DownloadLinksTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    private DownloadLinks $links;

    protected function setUp(): void
    {
        $this->links = new DownloadLinks(str_repeat('k', 32));
    }

    public function testATokenNamesItsExportUntilItExpires(): void
    {
        $token = $this->links->sign(self::ID, new DateTimeImmutable('@1800000000'));

        self::assertMatchesRegularExpression('/^'.self::ID.'\.1800000000\.[A-Za-z0-9_-]{43}$/D', $token);
        self::assertSame(self::ID, $this->links->verify($token, new DateTimeImmutable('@1799999999')));
        self::assertNull($this->links->verify($token, new DateTimeImmutable('@1800000000')));
        self::assertNull($this->links->verify($token, new DateTimeImmutable('@1800000001')));
    }

    public function testTheSignatureIsAnHmacOfTheIdentifierAndTheExpiry(): void
    {
        $expected = sodium_bin2base64(hash_hmac('sha256', self::ID.'.1800000000', hash_hkdf('sha256', str_repeat('k', 32), 32, 'phil_benefit_deliverables.download_link'), true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        self::assertSame(self::ID.'.1800000000.'.$expected, $this->links->sign(self::ID, new DateTimeImmutable('@1800000000')));
    }

    public function testAnotherSecretRefusesTheToken(): void
    {
        $token = new DownloadLinks(str_repeat('o', 32))->sign(self::ID, new DateTimeImmutable('@1800000000'));

        self::assertNull($this->links->verify($token, new DateTimeImmutable('@1700000000')));
    }

    /**
     * @return iterable<string, array{callable(string): string}>
     */
    public static function tampered(): iterable
    {
        yield 'another export' => [static fn (string $token): string => '1'.substr($token, 1)];
        yield 'a later expiry' => [static fn (string $token): string => str_replace('.1800000000.', '.1900000000.', $token)];
        yield 'a signature changed' => [static fn (string $token): string => substr($token, 0, -1).(str_ends_with($token, 'A') ? 'B' : 'A')];
        yield 'a new line after' => [static fn (string $token): string => $token."\n"];
        yield 'upper-case identifier' => [static fn (string $token): string => strtoupper(substr($token, 0, 32)).substr($token, 32)];
        yield 'a leading zero expiry' => [static fn (string $token): string => str_replace('.1800000000.', '.01800000000.', $token)];
        yield 'empty' => [static fn (string $token): string => ''];
        yield 'padded signature' => [static fn (string $token): string => $token.'='];
    }

    /**
     * @param callable(string): string $tamper
     */
    #[DataProvider('tampered')]
    public function testATamperedTokenIsRefused(callable $tamper): void
    {
        $token = $this->links->sign(self::ID, new DateTimeImmutable('@1800000000'));

        self::assertNull($this->links->verify($tamper($token), new DateTimeImmutable('@1700000000')));
    }

    public function testTheSecretIsLongEnough(): void
    {
        new DownloadLinks(str_repeat('k', 32));

        $this->expectException(InvalidDeliverable::class);
        $this->expectExceptionMessageIsOrContains('The secret of the download links is 32 bytes or more.');

        new DownloadLinks(str_repeat('k', 31));
    }

    public function testOnlyAnExportIsSigned(): void
    {
        $this->expectException(InvalidDeliverable::class);

        $this->links->sign('../etc/passwd', new DateTimeImmutable());
    }
}
