<?php

declare(strict_types=1);

namespace Phil\BounceParser\Tests\Unit\Mime;

use Phil\BounceParser\Mime\Headers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Headers::class)]
final class HeadersTest extends TestCase
{
    public function testUnfoldsTrimsAndKeepsTheOrder(): void
    {
        $headers = Headers::parse("Subject:  Hello\r\n  there \r\n\tagain\r\nX-A: 1\nnot a field\n x\nX-a:2\n: empty name\nBad Name: x\nX-Last: end");

        self::assertSame([
            ['Subject', 'Hello there again'],
            ['X-A', '1'],
            ['X-a', '2'],
            ['X-Last', 'end'],
        ], $headers->fields);
        self::assertSame('1', $headers->get('x-a'));
        self::assertSame(['1', '2'], $headers->all('X-A'));
        self::assertSame([], $headers->all('X-B'));
        self::assertTrue($headers->has('SUBJECT'));
        self::assertFalse($headers->has('From'));
        self::assertNull($headers->get('From'));
    }

    public function testKeepsAnEmptyValue(): void
    {
        $headers = Headers::parse("X-Empty:\nX-Next: 1");

        self::assertSame('', $headers->get('X-Empty'));
        self::assertTrue($headers->has('X-Empty'));
    }

    public function testStopsAtTheLimit(): void
    {
        self::assertSame([['A', '1'], ['B', '2']], Headers::parse("A: 1\nB: 2\nC: 3", 2)->fields);
        self::assertSame([['A', '1 more']], Headers::parse("A: 1\n more\nB: 2", 1)->fields);
        self::assertCount(500, Headers::parse(str_repeat("A: 1\n", 600))->fields);
    }

    public function testDecodesEncodedWords(): void
    {
        $headers = Headers::parse("Subject: =?utf-8?b?w4lsaXNl?= et =?iso-8859-1?q?Ren=E9?=\nFrom: plain\nX-Broken: =?utf-8?q?ok?= =?bad");

        self::assertSame('Élise et René', $headers->decoded('Subject'));
        self::assertSame('plain', $headers->decoded('From'));
        self::assertNull($headers->decoded('To'));
        self::assertStringStartsWith('ok', (string) $headers->decoded('X-Broken'));
    }
}
