<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\BrowserSecurityBundle\Domain\Enum\Disposition;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\ValueObject\PermissionsPolicy;
use Phil\BrowserSecurityBundle\Domain\ValueObject\StoredReport;
use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViolationReport::class)]
#[CoversClass(StoredReport::class)]
#[CoversClass(PermissionsPolicy::class)]
final class ValueObjectsTest extends TestCase
{
    public function testTheFingerprintOfAReport(): void
    {
        $report = new ViolationReport('https://example.com/a', 'inline', 'script-src-elem', Disposition::Report, 'https://example.com/app.js', 3, 7, 200, 'alert');

        self::assertSame(hash('sha256', "https://example.com/a\ninline\nscript-src-elem\nreport\nhttps://example.com/app.js\n3\n7"), $report->fingerprint());
        self::assertSame(hash('sha256', "https://example.com/a\ninline\nscript-src-elem\nenforce\n\n\n"), new ViolationReport('https://example.com/a', 'inline', 'script-src-elem')->fingerprint());
    }

    public function testAStoredReport(): void
    {
        $report = new ViolationReport('https://example.com/a', 'inline', 'script-src');
        $stored = new StoredReport($report, 3, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02'), '192.0.2.0');

        self::assertSame($report, $stored->report);
        self::assertSame(3, $stored->count);
        self::assertSame('192.0.2.0', $stored->client);
    }

    public function testAPermissionsPolicy(): void
    {
        self::assertSame(
            'camera=(), geolocation=(self "https://maps.example.com:8443"), fullscreen=(*), microphone=("http://a.example")',
            new PermissionsPolicy(['camera' => [], 'geolocation' => ['self', 'https://maps.example.com:8443'], 'fullscreen' => ['*'], 'microphone' => ['http://a.example']])->header,
        );
        self::assertSame('', new PermissionsPolicy([])->header);
    }

    /**
     * @return iterable<string, array{array<array-key, list<string>>, string}>
     */
    public static function invalidPermissions(): iterable
    {
        yield 'an upper-case feature' => [['Camera' => []], '"Camera" is not a feature of a permissions policy.'];
        yield 'a feature then text' => [["camera\n" => []], "\"camera\n\" is not a feature of a permissions policy."];
        yield 'text then a feature' => [["=\ncamera" => []], "\"=\ncamera\" is not a feature of a permissions policy."];
        yield 'a numeric feature' => [[0 => []], '"0" is not a feature of a permissions policy.'];
        yield 'a quoted origin' => [['camera' => ['"x"']], '""x"" is not an origin of a permissions policy: self, * or a scheme and a host.'];
        yield 'an origin with a path' => [['camera' => ['https://a.example/x']], '"https://a.example/x" is not an origin'];
        yield 'an origin then text' => [['camera' => ["https://a.example\n"]], "\"https://a.example\n\" is not an origin"];
        yield 'text then an origin' => [['camera' => ["x\nhttps://a.example"]], "\"x\nhttps://a.example\" is not an origin"];
    }

    /**
     * @param array<array-key, list<string>> $features
     */
    #[DataProvider('invalidPermissions')]
    public function testAnInvalidPermissionsPolicy(array $features, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        new PermissionsPolicy($features);
    }
}
