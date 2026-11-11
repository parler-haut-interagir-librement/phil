<?php

declare(strict_types=1);

namespace Phil\Phone\Tests\Unit\Metadata;

use Override;
use Phil\Phone\Exception\InvalidPhoneNumber;
use Phil\Phone\Metadata\NumberDesc;
use Phil\Phone\Metadata\NumberFormat;
use Phil\Phone\Metadata\Territories;
use Phil\Phone\Metadata\Territory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Territories::class)]
#[CoversClass(Territory::class)]
#[CoversClass(NumberDesc::class)]
#[CoversClass(NumberFormat::class)]
#[CoversClass(InvalidPhoneNumber::class)]
final class TerritoriesTest extends TestCase
{
    /**
     * The list of countries is built once a process: forgotten before each test, it is built again by the test.
     */
    #[Override]
    protected function setUp(): void
    {
        new ReflectionProperty(Territories::class, 'regions')->setValue(null, null);
    }

    public function testACountryIsFoundByItsCode(): void
    {
        $france = Territories::region('fr');

        self::assertSame(['FR', 33, '00', '0'], [$france->id, $france->countryCode, $france->internationalPrefix, $france->nationalPrefix]);
        self::assertSame($france, Territories::region('FR'));
        self::assertSame($france, Territories::of('FR', 33));
    }

    public function testTheRegionsOfACountryCodeHaveTheMainOneFirst(): void
    {
        self::assertSame(['US', 'AG'], \array_slice(Territories::regionsOf(1), 0, 2));
        self::assertSame(['GB', 'GG', 'IM', 'JE'], Territories::regionsOf(44));
        self::assertSame(['001'], Territories::regionsOf(800));
        self::assertSame([], Territories::regionsOf(999));
    }

    public function testEveryCountryIsListedOnceInOrder(): void
    {
        $regions = Territories::regions();

        self::assertSame(['AC', 'AD', 'AE'], \array_slice($regions, 0, 3));
        self::assertNotContains('001', $regions);
        self::assertContains('CA', $regions);
        self::assertSame($regions, array_values(array_unique($regions)));
        self::assertCount(245, $regions);
    }

    public function testOnlyACountryWithAPlanIsFound(): void
    {
        self::assertSame(Territories::regions(), Territories::regions());

        foreach (['001', 'ZZ', 'ca '] as $region) {
            try {
                Territories::region($region);
                self::fail($region);
            } catch (InvalidPhoneNumber $invalidPhoneNumber) {
                self::assertStringContainsString(strtoupper($region), $invalidPhoneNumber->getMessage());
            }
        }

        self::assertSame('CA', Territories::region('ca')->id);
    }

    public function testACountryCodeIsFormattedByItsMainPlan(): void
    {
        self::assertSame('US', Territories::main(1)?->id);
        self::assertSame(['001', 800], [Territories::main(800)?->id, Territories::main(800)?->countryCode]);
        self::assertSame(Territories::main(800), Territories::of('001', 800));
        self::assertNull(Territories::main(999));
    }
}
