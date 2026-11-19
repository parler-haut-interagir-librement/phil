<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Domain;

use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\Booth;
use Phil\MapBundle\Domain\ValueObject\FloorPlan;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FloorPlan::class)]
#[CoversClass(Booth::class)]
final class FloorPlanTest extends TestCase
{
    public function testABooth(): void
    {
        $booth = Booth::of('b1', 'Booth 1', 10, 20, 30, 40, 'res-1');

        self::assertSame(['b1', 'Booth 1', 10, 20, 30, 40, 'res-1'], [$booth->id, $booth->label, $booth->x, $booth->y, $booth->width, $booth->height, $booth->resource]);
        self::assertNull(Booth::of('b', 'B', 0, 0, 1, 1)->resource);
        self::assertSame(100, mb_strlen(Booth::of(str_repeat('b', 100), str_repeat('é', 100), 0, 0, 1, 1)->label));
    }

    /**
     * @return iterable<string, array{string, string, int, int, int, int, ?string, string}>
     */
    public static function refusedBooths(): iterable
    {
        yield 'empty identifier' => ['', 'B', 0, 0, 1, 1, null, 'A booth has an identifier of 1 to 100 bytes.'];
        yield 'blank identifier' => [' ', 'B', 0, 0, 1, 1, null, 'A booth has an identifier of 1 to 100 bytes.'];
        yield 'long identifier' => [str_repeat('b', 101), 'B', 0, 0, 1, 1, null, 'A booth has an identifier of 1 to 100 bytes.'];
        yield 'empty label' => ['b', ' ', 0, 0, 1, 1, null, 'A booth has a label of 1 to 100 characters.'];
        yield 'long label' => ['b', str_repeat('é', 101), 0, 0, 1, 1, null, 'A booth has a label of 1 to 100 characters.'];
        yield 'negative x' => ['b', 'B', -1, 0, 1, 1, null, 'The booth "b" covers a rectangle inside the plan, at least one pixel wide and high.'];
        yield 'negative y' => ['b', 'B', 0, -1, 1, 1, null, 'The booth "b" covers a rectangle inside the plan, at least one pixel wide and high.'];
        yield 'no width' => ['b', 'B', 0, 0, 0, 1, null, 'The booth "b" covers a rectangle inside the plan, at least one pixel wide and high.'];
        yield 'no height' => ['b', 'B', 0, 0, 1, 0, null, 'The booth "b" covers a rectangle inside the plan, at least one pixel wide and high.'];
        yield 'empty resource' => ['b', 'B', 0, 0, 1, 1, '', 'The resource of the booth "b" is named, or null.'];
    }

    #[DataProvider('refusedBooths')]
    public function testARefusedBooth(string $id, string $label, int $x, int $y, int $width, int $height, ?string $resource, string $message): void
    {
        $this->expectExceptionObject(InvalidMap::because($message));

        Booth::of($id, $label, $x, $y, $width, $height, $resource);
    }

    public function testAPlanKeepsItsBoothsInOrder(): void
    {
        $plan = FloorPlan::of(SiteAddress::of('/hall.png'), 400, 200, (static function (): iterable {
            yield Booth::of('b2', 'Two', 0, 0, 400, 200);
            yield Booth::of('b1', 'One', 10, 10, 10, 10);
        })(), SiteAddress::of('/hall/{z}/{x}/{y}.png'));

        self::assertSame(['b2', 'b1'], array_map(static fn (Booth $booth): string => $booth->id, $plan->booths));
        self::assertSame(['/hall.png', 400, 200, '/hall/{z}/{x}/{y}.png'], [$plan->image->value, $plan->width, $plan->height, $plan->tiles?->value]);
        self::assertNull(FloorPlan::of(SiteAddress::of('/a.png'), 1, 1)->tiles);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function zooms(): iterable
    {
        yield 'small' => [180, 90, 0];
        yield 'one wider' => [181, 90, 1];
        yield 'one higher' => [180, 91, 1];
        yield 'exactly twice' => [360, 180, 1];
        yield 'large' => [10_000, 2000, 6];
        yield 'tall' => [100, 5000, 6];
    }

    #[DataProvider('zooms')]
    public function testTheNativeZoomFitsTheImageIn180By90Units(int $width, int $height, int $zoom): void
    {
        self::assertSame($zoom, FloorPlan::of(SiteAddress::of('/a.png'), $width, $height)->nativeZoom());
        self::assertSame($zoom, FloorPlan::zoomFor($width, $height));
    }

    public function testAPixelLiesSouthAndEastOfTheOrigin(): void
    {
        $plan = FloorPlan::of(SiteAddress::of('/a.png'), 720, 360);

        self::assertSame([-0.0, 0.0], $plan->position(0, 0));
        self::assertSame([-90.0, 180.0], $plan->position(720, 360));
        self::assertSame([-25.0, 50.0], $plan->position(200, 100));
    }

    /**
     * @return iterable<string, array{int, int, list<Booth>, ?string, string}>
     */
    public static function refusedPlans(): iterable
    {
        yield 'no width' => [0, 10, [], null, 'A floor plan is 1 to 1000000 pixels wide and high, not 0 × 10.'];
        yield 'no height' => [10, 0, [], null, 'A floor plan is 1 to 1000000 pixels wide and high, not 10 × 0.'];
        yield 'too wide' => [1_000_001, 10, [], null, 'A floor plan is 1 to 1000000 pixels wide and high, not 1000001 × 10.'];
        yield 'too high' => [10, 1_000_001, [], null, 'A floor plan is 1 to 1000000 pixels wide and high, not 10 × 1000001.'];
        yield 'past the right edge' => [10, 10, [Booth::of('b', 'B', 5, 0, 6, 1)], null, 'The booth "b" goes past the edge of the plan.'];
        yield 'past the bottom edge' => [10, 10, [Booth::of('b', 'B', 0, 5, 1, 6)], null, 'The booth "b" goes past the edge of the plan.'];
        yield 'twice' => [10, 10, [Booth::of('b', 'B', 0, 0, 1, 1), Booth::of('b', 'C', 1, 1, 1, 1)], null, 'Two booths of the plan are named "b".'];
        yield 'tiles without z' => [10, 10, [], '/t/{x}/{y}', 'The tile template of the plan has no "{z}".'];
        yield 'tiles without x' => [10, 10, [], '/t/{z}/{y}', 'The tile template of the plan has no "{x}".'];
        yield 'tiles without y' => [10, 10, [], '/t/{z}/{x}', 'The tile template of the plan has no "{y}".'];
    }

    /**
     * @param list<Booth> $booths
     */
    #[DataProvider('refusedPlans')]
    public function testARefusedPlan(int $width, int $height, array $booths, ?string $tiles, string $message): void
    {
        $this->expectExceptionObject(InvalidMap::because($message));

        FloorPlan::of(SiteAddress::of('/a.png'), $width, $height, $booths, null === $tiles ? null : SiteAddress::of($tiles));
    }

    public function testTheBoundsOfAPlanAreAccepted(): void
    {
        $booths = [];

        for ($i = 0; $i < FloorPlan::MAX_BOOTHS; ++$i) {
            $booths[] = Booth::of('b'.$i, 'B', 0, 0, 1_000_000, 1_000_000);
        }

        self::assertCount(2000, FloorPlan::of(SiteAddress::of('/a.png'), 1_000_000, 1_000_000, $booths)->booths);

        $booths[] = Booth::of('one-more', 'B', 0, 0, 1, 1);
        $this->expectExceptionObject(InvalidMap::because('A floor plan holds 2000 booths at most.'));
        FloorPlan::of(SiteAddress::of('/a.png'), 1_000_000, 1_000_000, $booths);
    }
}
