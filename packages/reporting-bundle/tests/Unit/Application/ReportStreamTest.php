<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\ReportingBundle\Application\Report\ReportStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportStream::class)]
final class ReportStreamTest extends TestCase
{
    public function testWrittenRewoundAndRead(): void
    {
        $stream = ReportStream::temporary();
        $stream->write('ab');
        $stream->writeFields(['x "y"', 1, 2.5, null, 'a;b', 'é'], ';');
        $stream->write('c');

        self::assertSame('', $stream->contents());

        $stream->rewind();

        self::assertSame("ab\"x \"\"y\"\"\";1;2.5;;\"a;b\";é\r\nc", $stream->contents());
        self::assertSame('', $stream->contents());
    }

    public function testCopiedFromWhereItStands(): void
    {
        $source = ReportStream::temporary();
        $source->write('header,rest');
        $source->rewind();
        $source->write('HEADER');

        $target = ReportStream::of(fopen('php://memory', 'w+'));

        self::assertSame(5, $source->copyTo($target));
        self::assertSame(0, $source->copyTo($target));

        $target->rewind();

        self::assertSame(',rest', $target->contents());
    }

    public function testClosed(): void
    {
        $handle = fopen('php://memory', 'w+');
        ReportStream::of($handle)->close();

        self::assertFalse(\is_resource($handle));
    }

    public function testOnlyAnOpenStream(): void
    {
        $closed = fopen('php://memory', 'r');
        self::assertIsResource($closed);
        fclose($closed);

        foreach ([
            'A report stream needs an open stream, bool given.' => false,
            'A report stream needs an open stream, resource (closed) given.' => $closed,
            'A report stream needs an open stream, resource (stream-context) given.' => stream_context_create(),
        ] as $message => $handle) {
            try {
                ReportStream::of($handle);
                self::fail('Accepted.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame($message, $invalidArgumentException->getMessage());
            }
        }
    }
}
