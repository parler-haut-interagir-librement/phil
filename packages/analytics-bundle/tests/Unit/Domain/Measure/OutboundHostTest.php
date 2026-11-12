<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Measure;

use Phil\AnalyticsBundle\Domain\Measure\OutboundHost;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutboundHost::class)]
final class OutboundHostTest extends TestCase
{
    public function testAnyHostIsKeptInLowerCase(): void
    {
        self::assertSame('www.example.org', OutboundHost::valueOf('WWW.Example.org', []));
        self::assertSame('a-b.example.org', OutboundHost::valueOf('a-b.example.org', []));
    }

    public function testTheLongestHostFitsACounter(): void
    {
        $host = str_repeat('a', 61).'.'.str_repeat('b', 61).'.exam';

        self::assertSame($host, OutboundHost::valueOf($host, []));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAHost(): iterable
    {
        yield 'empty' => [''];
        yield 'no dot' => ['localhost'];
        yield 'too long' => [str_repeat('a', 61).'.'.str_repeat('b', 61).'.examp'];
        yield 'a label too long' => [str_repeat('a', 64).'.org'];
        yield 'a leading dash' => ['-a.example.org'];
        yield 'a trailing dash' => ['a-.example.org'];
        yield 'a path' => ['example.org/a'];
        yield 'a trailing newline' => ["example.org\n"];
        yield 'text before' => ['a b.example.org'];
    }

    #[DataProvider('notAHost')]
    public function testWhatIsNotAHostCountsNothing(string $host): void
    {
        self::assertNull(OutboundHost::valueOf($host, []));
    }

    public function testASiteOwnHostIsNotOutbound(): void
    {
        $origins = [Origin::fromString('https://a.example'), Origin::fromString('https://b.example')];

        self::assertNull(OutboundHost::valueOf('B.example', $origins));
        self::assertSame('c.example', OutboundHost::valueOf('c.example', $origins));
    }
}
