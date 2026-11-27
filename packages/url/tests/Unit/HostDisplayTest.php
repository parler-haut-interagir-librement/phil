<?php

declare(strict_types=1);

namespace Phil\Url\Tests\Unit;

use Phil\Url\HostDisplay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Spoofchecker;

#[CoversClass(HostDisplay::class)]
final class HostDisplayTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function hostsWithAChecker(): iterable
    {
        yield 'a plain host' => ['example.com', 'example.com'];
        yield 'an international host' => ['xn--bcher-kva.example', 'bücher.example'];
        yield 'a host in one script' => ['xn--80adxhks.xn--p1ai', 'москва.рф'];
        yield 'a host passing for another' => ['xn--pple-43d.com', 'xn--pple-43d.com'];
        yield 'a label no ASCII form decodes' => ['xn--a.example', 'xn--a.example'];
    }

    #[DataProvider('hostsWithAChecker')]
    #[RequiresPhpExtension('intl')]
    public function testAHostReadsInItsOwnLettersWhenTheCheckerTrustsThem(string $host, string $display): void
    {
        self::assertSame($display, new HostDisplay(new Spoofchecker())->show($host));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function hostsWithoutAChecker(): iterable
    {
        yield 'a plain host' => ['example.com', 'example.com'];
        yield 'an international host' => ['xn--bcher-kva.example', 'xn--bcher-kva.example'];
        yield 'a host in one script' => ['xn--80adxhks.xn--p1ai', 'xn--80adxhks.xn--p1ai'];
        yield 'a host passing for another' => ['xn--pple-43d.com', 'xn--pple-43d.com'];
    }

    #[DataProvider('hostsWithoutAChecker')]
    public function testWithoutACheckerEveryInternationalHostStaysInAscii(string $host, string $display): void
    {
        self::assertSame($display, new HostDisplay(null)->show($host));
    }

    #[RequiresPhpExtension('intl')]
    public function testAPlainHostIsNeverCheckedForSpoofing(): void
    {
        $checker = new class extends Spoofchecker {
            /** @var list<string> */
            public array $checked = [];

            public function isSuspicious(string $string, mixed &$errorCode = null): bool
            {
                $this->checked[] = $string;

                return parent::isSuspicious($string);
            }
        };
        $display = new HostDisplay($checker);

        self::assertSame('example.com', $display->show('example.com'));
        self::assertSame('bücher.example', $display->show('xn--bcher-kva.example'));
        self::assertSame(['bücher.example'], $checker->checked);
    }

    public function testTheSharedDisplayIsBuiltOnce(): void
    {
        self::assertSame(HostDisplay::shared(), HostDisplay::shared());
    }

    #[RequiresPhpExtension('intl')]
    public function testTheSharedDisplayHasAChecker(): void
    {
        self::assertSame('bücher.example', HostDisplay::shared()->show('xn--bcher-kva.example'));
        self::assertSame('xn--pple-43d.com', HostDisplay::shared()->show('xn--pple-43d.com'));
    }

    public function testTheSharedDisplayHasNoCheckerWithoutIntl(): void
    {
        if (\extension_loaded('intl')) {
            self::markTestSkipped('Run on PHP without ext-intl, where the polyfill converts the hosts.');
        }

        self::assertSame('xn--bcher-kva.example', HostDisplay::shared()->show('xn--bcher-kva.example'));
    }
}
