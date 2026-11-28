<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Exception;

use LogicException;
use Phil\BulkActions\Exception\ItemRefused;
use Phil\BulkActions\Exception\VersionConflict;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ItemRefused::class)]
#[CoversClass(VersionConflict::class)]
final class ExceptionsTest extends TestCase
{
    public function testARefusalKeepsItsReasonAndSaysIt(): void
    {
        $refused = ItemRefused::because('products.refused.archived');

        self::assertSame('products.refused.archived', $refused->reason);
        self::assertSame('The item was refused: products.refused.archived.', $refused->getMessage());
    }

    public function testAConflictKeepsItsCauseAndHasNoCode(): void
    {
        $previous = new LogicException('stale');
        $conflict = VersionConflict::create($previous);

        self::assertSame('The item changed since it was read.', $conflict->getMessage());
        self::assertSame(0, $conflict->getCode());
        self::assertSame($previous, $conflict->getPrevious());
        self::assertNull(VersionConflict::create()->getPrevious());
    }
}
