<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit;

use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\Frame;
use Phil\ErrorTracking\Internal\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Frame::class)]
#[CoversClass(ExceptionRecord::class)]
#[CoversClass(Text::class)]
final class FrameTest extends TestCase
{
    public function testAFrameKeepsItsValues(): void
    {
        $frame = new Frame('src/Export.php', 12, 'App\Export::run', true);

        self::assertSame('src/Export.php', $frame->file);
        self::assertSame(12, $frame->line);
        self::assertSame('App\Export::run', $frame->function);
        self::assertTrue($frame->inApp);
        self::assertFalse(new Frame('a.php', 1)->inApp);
        self::assertNull(new Frame('a.php', 1)->function);
    }

    public function testTheLabel(): void
    {
        self::assertSame('App\Export::run (src/Export.php:12)', new Frame('src/Export.php', 12, 'App\Export::run')->label());
        self::assertSame('bin/console:3', new Frame('bin/console', 3)->label());
    }

    public function testClosuresAndAnonymousClassesLoseTheirPathAndLine(): void
    {
        self::assertSame('{closure}', new Frame('a.php', 1, '{closure:App\Job::run():12}')->function);
        self::assertSame('App\Job::{closure}', new Frame('a.php', 1, 'App\Job::{closure:/app/src/Job.php:30}')->function);
        self::assertSame('class@anonymous::handle', new Frame('a.php', 1, "class@anonymous\0/app/src/Job.php:7\$0::handle")->function);
        self::assertSame('{closure}', new Frame('a.php', 1, "{closure:class@anonymous\0/app/x.php:3\$0::m():4}")->function);
        self::assertSame('Throwable@anonymous', new ExceptionRecord("Throwable@anonymous\0/app/x.php:3\$1f", '')->type);
    }

    public function testTextsAreBoundedAndMadeValid(): void
    {
        $frame = new Frame(str_repeat('é', Frame::MAX_FILE_LENGTH + 5), 1, str_repeat('f', Frame::MAX_FUNCTION_LENGTH + 5));

        self::assertSame(str_repeat('é', Frame::MAX_FILE_LENGTH), $frame->file);
        self::assertSame(str_repeat('f', Frame::MAX_FUNCTION_LENGTH), $frame->function);
        self::assertSame('a?b', new Frame("a\xFFb", 1)->file);
        self::assertSame(1024, Frame::MAX_FILE_LENGTH);
        self::assertSame(255, Frame::MAX_FUNCTION_LENGTH);
    }

    public function testAnExceptionRecord(): void
    {
        $vendor = new Frame('vendor/a.php', 1);
        $first = new Frame('src/A.php', 2, null, true);
        $record = new ExceptionRecord('LogicException', str_repeat('m', ExceptionRecord::MAX_MESSAGE_LENGTH + 1), [$vendor, $first, new Frame('src/B.php', 3, null, true)]);

        self::assertSame('LogicException', $record->type);
        self::assertSame(str_repeat('m', 2048), $record->message);
        self::assertSame($first, $record->culprit());
        self::assertNull(new ExceptionRecord('E', '', [$vendor])->culprit());
        self::assertSame(str_repeat('T', 255), new ExceptionRecord(str_repeat('T', 300), '')->type);
        self::assertSame(255, ExceptionRecord::MAX_TYPE_LENGTH);
    }
}
