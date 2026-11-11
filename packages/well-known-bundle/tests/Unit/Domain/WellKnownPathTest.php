<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Domain;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\Exception\WellKnownException;
use Phil\WellKnownBundle\Domain\ValueObject\WellKnownPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WellKnownPath::class)]
#[CoversClass(InvalidValue::class)]
final class WellKnownPathTest extends TestCase
{
    public function testAName(): void
    {
        $path = WellKnownPath::of('security.txt');

        self::assertSame(['/.well-known/security.txt', 'phil_well_known_security_txt'], [$path->path, $path->route]);
        self::assertSame(['/.well-known/matrix/server', 'phil_well_known_matrix_server'], [WellKnownPath::of('matrix/server')->path, WellKnownPath::of('matrix/server')->route]);
        self::assertSame('phil_well_known_a_b_c', WellKnownPath::of('A--B~C')->route);
        self::assertSame('phil_well_known_x', WellKnownPath::of('x~')->route);
    }

    public function testAnAbsolutePath(): void
    {
        $path = WellKnownPath::of('/nodeinfo/2.1', 'nodeinfo_document');

        self::assertSame(['/nodeinfo/2.1', 'nodeinfo_document'], [$path->path, $path->route]);
        self::assertSame('phil_well_known_nodeinfo_2_1', WellKnownPath::of('/nodeinfo/2.1')->route);
    }

    public function testTheLongestPath(): void
    {
        self::assertSame(WellKnownPath::PREFIX.str_repeat('a', 200), WellKnownPath::of(str_repeat('a', 200), 'long')->path);
        self::assertSame('/'.str_repeat('a', 200), WellKnownPath::of('/'.str_repeat('a', 200), 'long')->path);
        self::assertSame(str_repeat('r', 128), WellKnownPath::of('a', str_repeat('r', 128))->route);
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function badPaths(): iterable
    {
        yield 'empty' => ['', null, '"" is not a well-known path'];
        yield 'a slash' => ['/', null, 'not a well-known path'];
        yield 'a dot segment' => ['a/../b', null, 'not a well-known path'];
        yield 'a hidden segment' => ['.hidden', null, 'not a well-known path'];
        yield 'an empty segment' => ['a//b', null, 'not a well-known path'];
        yield 'a trailing slash' => ['a/', null, 'not a well-known path'];
        yield 'a query' => ['a?b', null, 'not a well-known path'];
        yield 'a space' => ['a b', null, 'not a well-known path'];
        yield 'a new line' => ["a\n", null, 'not a well-known path'];
        yield 'under the prefix' => ['/.well-known/a', null, 'not a well-known path'];
        yield 'too long' => [str_repeat('a', 201), 'long', '200 characters at most'];
        yield 'too long, absolute' => ['/'.str_repeat('a', 201), 'long', 'not a well-known path'];
        yield 'a bad route' => ['a', 'a-b', '"a-b" is not a route name'];
        yield 'an empty route' => ['a', '', 'not a route name'];
        yield 'a long route' => ['a', str_repeat('r', 129), 'not a route name'];
        yield 'a long derived route' => [str_repeat('a', 113), null, 'not a route name'];
        yield 'a route with a new line' => ['a', "r\n", 'not a route name'];
    }

    #[DataProvider('badPaths')]
    public function testABadPathIsRefused(string $path, ?string $route, string $message): void
    {
        $this->expectException(WellKnownException::class);
        $this->expectExceptionMessageIsOrContains($message);

        WellKnownPath::of($path, $route);
    }

    public function testADerivedRouteFitsAt128(): void
    {
        self::assertSame('phil_well_known_'.str_repeat('a', 112), WellKnownPath::of(str_repeat('a', 112))->route);
    }
}
