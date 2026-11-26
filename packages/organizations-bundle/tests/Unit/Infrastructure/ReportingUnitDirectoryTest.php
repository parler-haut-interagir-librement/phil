<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use Phil\OrganizationsBundle\Infrastructure\Reporting\ReportingUnitDirectory;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use Phil\ReportingBundle\Domain\Training\UnitNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The tree of units as the unit directory of phil/reporting-bundle: the path of a unit from its root, archived units
 * included, and none for a unit the tree does not know.
 */
#[CoversClass(ReportingUnitDirectory::class)]
final class ReportingUnitDirectoryTest extends TestCase
{
    public function testThePathOfAUnitRunsFromItsRootToTheUnit(): void
    {
        $stage = new Stage();
        $acme = $stage->acme();
        $owner = Stage::member('ann');
        $sales = $stage->units->create($acme->id, $owner, 'Sales');
        $east = $stage->units->create($acme->id, $owner, 'East', $sales->id);
        $shop = $stage->units->create($acme->id, $owner, 'Shop', $east->id);
        $directory = new ReportingUnitDirectory($stage->tree);

        self::assertEquals([new UnitNode($sales->id->value, 'Sales'), new UnitNode($east->id->value, 'East'), new UnitNode($shop->id->value, 'Shop')], $directory->path($shop->id->value));
        self::assertEquals([new UnitNode($sales->id->value, 'Sales')], $directory->path($sales->id->value));

        $stage->units->archive($east->id, $owner);
        self::assertEquals([new UnitNode($sales->id->value, 'Sales'), new UnitNode($east->id->value, 'East'), new UnitNode($shop->id->value, 'Shop')], $directory->path($shop->id->value), 'An archived unit keeps its place.');

        self::assertSame([], $directory->path('01JUNKNOWNUNIT0000000000000'));
        self::assertSame([], $directory->path('not a unit identifier'));
        self::assertSame([], $directory->path(''));
    }
}
