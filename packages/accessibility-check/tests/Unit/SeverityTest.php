<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit;

use Phil\AccessibilityCheck\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Severity::class)]
final class SeverityTest extends TestCase
{
    /**
     * @return iterable<array{Severity, Severity, bool}>
     */
    public static function pairs(): iterable
    {
        yield [Severity::Error, Severity::Error, true];
        yield [Severity::Error, Severity::Warning, true];
        yield [Severity::Error, Severity::Notice, true];
        yield [Severity::Warning, Severity::Error, false];
        yield [Severity::Warning, Severity::Warning, true];
        yield [Severity::Warning, Severity::Notice, true];
        yield [Severity::Notice, Severity::Error, false];
        yield [Severity::Notice, Severity::Warning, false];
        yield [Severity::Notice, Severity::Notice, true];
    }

    #[DataProvider('pairs')]
    public function testASeverityIsAtLeastItselfAndWhatIsLessGrave(Severity $severity, Severity $minimum, bool $expected): void
    {
        self::assertSame($expected, $severity->isAtLeast($minimum));
    }
}
