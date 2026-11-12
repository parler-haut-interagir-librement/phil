<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit;

use Phil\AccessibilityCheck\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Location::class)]
final class LocationTest extends TestCase
{
    public function testALocationIsDescribedByWhatIsKnown(): void
    {
        self::assertSame('the content', new Location()->describe());
        self::assertSame('line 3, p > img', new Location(3, 'p > img', '<img>')->describe());
        self::assertSame('block "a1" (image), blocks[2].data.caption, a', new Location(null, 'a', null, 'a1', 'image', 'blocks[2].data.caption')->describe());
        self::assertSame('block "a1" (?)', new Location(block: 'a1')->describe());
    }

    public function testOnlyTheKnownFieldsAreWritten(): void
    {
        self::assertSame([], new Location()->toArray());
        self::assertSame(['line' => 3, 'element' => 'img', 'snippet' => '<img>'], new Location(3, 'img', '<img>')->toArray());
        self::assertSame(
            ['element' => 'a', 'block' => 'a1', 'blockType' => 'image', 'pointer' => 'blocks[0]'],
            new Location(null, 'a', null, 'a1', 'image', 'blocks[0]')->toArray(),
        );
    }
}
