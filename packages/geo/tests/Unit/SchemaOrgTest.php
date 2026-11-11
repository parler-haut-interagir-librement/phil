<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit;

use Phil\Geo\Coordinates;
use Phil\Geo\SchemaOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaOrg::class)]
final class SchemaOrgTest extends TestCase
{
    public function testCoordinatesAreGeoCoordinates(): void
    {
        self::assertSame(['@type' => 'GeoCoordinates', 'latitude' => 45.5019, 'longitude' => -73.5674], SchemaOrg::of(Coordinates::of(45.5019, -73.5674)));
    }
}
