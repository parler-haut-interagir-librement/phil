<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Unit\Infrastructure;

use Locale;
use Phil\Measurement\ChannelConversions;
use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Cldr\UnitPreferences;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Twig\MeasurementExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeasurementExtension::class)]
final class MeasurementExtensionTest extends TestCase
{
    private string $locale;

    private UnitRegistry $registry;

    private MeasurementExtension $extension;

    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('en_US');
        $this->registry = UnitRegistry::standard();
        $this->extension = new MeasurementExtension(
            UnitFormatter::cldr(),
            new ChannelConversions($this->registry, ['marketplace' => ['mass' => '[lb_av]']]),
            UnitPreferences::cldr($this->registry),
        );
    }

    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testItFormatsAMeasurementInTheLocale(): void
    {
        $weight = $this->registry->measurement('12.345', 'kg');

        self::assertSame('12.34 kg', $this->extension->format($weight));
        self::assertSame('12.3 kilograms', $this->extension->format($weight, 'long', 1));
        self::assertSame("12,345\u{a0}kilogrammes", $this->extension->format($weight, 'long', 3, 'fr_CA'));
        self::assertSame('', $this->extension->format(null));
        self::assertSame('', $this->extension->format([]));
    }

    public function testItFormatsTheParts(): void
    {
        $parts = $this->extension->preferred($this->registry->measurement('178', 'cm'), 'person-height', null, 0);

        self::assertSame('5 ft, 10 in', $this->extension->format($parts));
        self::assertSame(['1 m', '78 cm'], array_map(strval(...), $this->extension->preferred($this->registry->measurement('178', 'cm'), 'person-height', 'FR', 0)));
        self::assertSame([], $this->extension->preferred(null));
    }

    public function testTheRegionIsTheLocalesOrTheWorld(): void
    {
        $height = $this->registry->measurement('178', 'cm');

        Locale::setDefault('fr_FR');
        self::assertSame(['1 m', '78 cm'], array_map(strval(...), $this->extension->preferred($height, 'person-height', fractionDigits: 0)));

        Locale::setDefault('fr');
        self::assertSame(['178 cm'], array_map(strval(...), $this->extension->preferred($height, 'person-height')));

        Locale::setDefault('es_419');
        self::assertSame(['178 cm'], array_map(strval(...), $this->extension->preferred($height, 'person-height')));
    }

    public function testOnlyTwoCapitalLettersAreARegion(): void
    {
        $height = $this->registry->measurement('178', 'cm');

        self::assertSame(['1 m', '78 cm'], array_map(strval(...), $this->extension->preferred($height, 'person-height', 'FR', 0)));

        foreach (['XFR', 'FRX', "FR\n", 'fr'] as $region) {
            self::assertSame(['178 cm'], array_map(strval(...), $this->extension->preferred($height, 'person-height', $region, 0)), $region);
        }
    }

    public function testItConvertsToTheUnitOfAChannel(): void
    {
        self::assertSame('16 [oz_av]', (string) $this->extension->inChannel($this->registry->measurement('16', '[oz_av]'), 'storefront'));
        self::assertSame('1', $this->extension->inChannel($this->registry->measurement('16', '[oz_av]'), 'marketplace')?->toDecimal(2));
        self::assertNull($this->extension->inChannel(null, 'marketplace'));
    }
}
