<?php

declare(strict_types=1);

namespace Phil\Measurement\Tests\Unit;

use Phil\Measurement\ChannelConversions;
use Phil\Measurement\Exception\InvalidUnitDefinition;
use Phil\Measurement\Exception\UnknownUnit;
use Phil\Measurement\UnitRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChannelConversions::class)]
final class ChannelConversionsTest extends TestCase
{
    public function testEachChannelShowsAFamilyInItsUnit(): void
    {
        $registry = UnitRegistry::standard();
        $conversions = new ChannelConversions($registry, [
            'storefront' => ['mass' => 'kg', 'length' => 'cm'],
            'marketplace' => ['mass' => '[lb_av]'],
        ]);
        $weight = $registry->measurement('500', 'g');

        self::assertSame('0.5 kg', $conversions->convert($weight, 'storefront')->toString());
        self::assertSame('1.1', $conversions->convert($weight, 'marketplace')->toDecimal(1));
        self::assertSame($weight, $conversions->convert($weight, 'point-of-sale'));
        $length = $registry->measurement('2', 'm');
        self::assertSame($length, $conversions->convert($length, 'marketplace'));
        self::assertSame('[lb_av]', $conversions->unitFor('marketplace', 'mass')?->code);
        self::assertNull($conversions->unitFor('marketplace', 'length'));
        self::assertSame(['storefront', 'marketplace'], $conversions->channels());
    }

    public function testAChannelNamedByANumberStaysAString(): void
    {
        // PHP keeps a key of digits as an integer: the channel is a string again when it is read back.
        $conversions = new ChannelConversions(UnitRegistry::standard(), ['2024' => ['mass' => 'kg'], 'web' => ['mass' => 'g']]);

        self::assertSame(['2024', 'web'], $conversions->channels());
    }

    public function testAUnitOfAnotherFamilyIsRefused(): void
    {
        $this->expectException(InvalidUnitDefinition::class);
        $this->expectExceptionMessageIsOrContains('The unit "m" of the channel "storefront" is not a unit of the family "mass".');
        new ChannelConversions(UnitRegistry::standard(), ['storefront' => ['mass' => 'm']]);
    }

    public function testAnUnknownFamilyIsRefused(): void
    {
        $this->expectException(UnknownUnit::class);
        new ChannelConversions(UnitRegistry::standard(), ['storefront' => ['weight' => 'kg']]);
    }
}
