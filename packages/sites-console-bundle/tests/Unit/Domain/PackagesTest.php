<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Domain;

use Phil\SitesConsoleBundle\Domain\Version\Packages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Packages::class)]
final class PackagesTest extends TestCase
{
    public function testNames(): void
    {
        self::assertTrue(Packages::isName('phil/url'));
        self::assertTrue(Packages::isName('a.b_c-d/e--f.g_h'));
        self::assertTrue(Packages::isName('a/'.str_repeat('b', 212)));
        self::assertFalse(Packages::isName('a/'.str_repeat('b', 213)));
        self::assertFalse(Packages::isName('Phil/url'));
        self::assertFalse(Packages::isName('phil'));
        self::assertFalse(Packages::isName('phil/url/x'));
        self::assertFalse(Packages::isName('-phil/url'));
        self::assertFalse(Packages::isName('phil/url-'));
        self::assertFalse(Packages::isName("phil/url\n"));
        self::assertFalse(Packages::isName('xphil/url!'));
    }

    public function testVersions(): void
    {
        self::assertTrue(Packages::isVersion('v8.2.0'));
        self::assertTrue(Packages::isVersion('dev-main'));
        self::assertTrue(Packages::isVersion('0.1.x-dev'));
        self::assertTrue(Packages::isVersion('1.0.0+build/7'));
        self::assertTrue(Packages::isVersion(str_repeat('1', 64)));
        self::assertFalse(Packages::isVersion(str_repeat('1', 65)));
        self::assertFalse(Packages::isVersion(''));
        self::assertFalse(Packages::isVersion('.1'));
        self::assertFalse(Packages::isVersion('1 0'));
        self::assertFalse(Packages::isVersion("1.0\n"));
        self::assertFalse(Packages::isVersion('1.0<'));
    }

    #[DataProvider('comparisons')]
    public function testTheOrderOfVersions(string $first, string $second, ?int $expected): void
    {
        self::assertSame($expected, Packages::compare($first, $second));
    }

    /**
     * @return iterable<string, array{string, string, ?int}>
     */
    public static function comparisons(): iterable
    {
        yield 'older' => ['1.2.3', '1.10.0', -1];
        yield 'newer' => ['2.0', '1.9.9', 1];
        yield 'equal with a v' => ['v1.2.3', '1.2.3', 0];
        yield 'pre-release before release' => ['1.0.0-alpha', '1.0.0', -1];
        yield 'alpha before beta' => ['0.1.0-alpha', '0.1.0-beta', -1];
        yield 'branch has no order' => ['dev-main', '1.0.0', null];
        yield 'branch on the right' => ['1.0.0', 'dev-main', null];
        yield 'v alone is no version' => ['v', '1', null];
        yield 'letters first' => ['beta1', '1', null];
    }

    public function testOrderedVersions(): void
    {
        self::assertTrue(Packages::isOrdered('v1.2'));
        self::assertFalse(Packages::isOrdered('dev-main'));
    }

    public function testPatterns(): void
    {
        self::assertTrue(Packages::matches('phil/url', ['phil/*']));
        self::assertTrue(Packages::matches('phil/url', ['symfony/*', 'phil/url']));
        self::assertTrue(Packages::matches('any/thing', ['*']));
        self::assertFalse(Packages::matches('phil/url', []));
        self::assertFalse(Packages::matches('phil/url-bundle', ['phil/url']));
        self::assertFalse(Packages::matches('xphil/url', ['phil/*']));
        self::assertFalse(Packages::matches('phil.url', ['phil/url']));
        self::assertTrue(Packages::matches('mail.footer', ['mail.*']));
        self::assertFalse(Packages::matches('mailxfooter', ['mail.footer']));
    }
}
