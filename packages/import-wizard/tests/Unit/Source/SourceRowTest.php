<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Source;

use Phil\ImportWizard\Source\SourceRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceRow::class)]
final class SourceRowTest extends TestCase
{
    public function testARowIsWellFormedUnlessTold(): void
    {
        self::assertFalse(new SourceRow(2, ['a'])->malformed);
        self::assertTrue(new SourceRow(2, ['a'], true)->malformed);
    }

    public function testARowOfEmptyOrBlankCellsIsBlank(): void
    {
        self::assertTrue(new SourceRow(2, ['', ' ', "\t"])->isBlank());
        self::assertFalse(new SourceRow(2, ['', 'a'])->isBlank());
    }
}
