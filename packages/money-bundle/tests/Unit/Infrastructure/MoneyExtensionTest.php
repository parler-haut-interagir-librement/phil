<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit\Infrastructure;

use Locale;
use NumberFormatter;
use Override;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\MoneyBundle\Infrastructure\Twig\MoneyExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(MoneyExtension::class)]
final class MoneyExtensionTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testMoneyIsFormattedInALocale(): void
    {
        $extension = new MoneyExtension();

        self::assertSame("1\u{A0}234,50\u{A0}\$", $extension->formatMoney(Money::of(123_450, Currency::of('CAD')), 'fr_CA'));
        self::assertSame('-CA$0.05', $extension->formatMoney(Money::of(-5, Currency::of('CAD')), 'en'));
        self::assertSame('¥1,200', $extension->formatMoney(Money::of(1_200, Currency::of('JPY')), 'en'));
        self::assertSame('', $extension->formatMoney(null));
    }

    public function testTheDefaultLocaleIsUsed(): void
    {
        Locale::setDefault('en');

        self::assertSame('CA$12.34', new MoneyExtension()->formatMoney(Money::of(1_234, Currency::of('CAD'))));
    }

    public function testOneExtensionKeepsEachLocaleApart(): void
    {
        $extension = new MoneyExtension();
        $price = Money::of(1_234, Currency::of('CAD'));

        Locale::setDefault('en');
        self::assertSame('CA$12.34', $extension->formatMoney($price));
        self::assertSame("12,34\u{A0}\$", $extension->formatMoney($price, 'fr_CA'));

        Locale::setDefault('fr_CA');
        self::assertSame("12,34\u{A0}\$", $extension->formatMoney($price));
        self::assertSame('CA$12.34', $extension->formatMoney($price, 'en'));
    }

    public function testTheFilterIsDeclared(): void
    {
        $twig = new Environment(new ArrayLoader(['price' => "{{ price|format_money('en') }}"]));
        $twig->addExtension(new AttributeExtension(MoneyExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([MoneyExtension::class => static fn (): MoneyExtension => new MoneyExtension()]));

        self::assertSame('$12.34', $twig->render('price', ['price' => Money::of(1_234, Currency::of('USD'))]));
    }

    public function testAnAmountIsAMonetaryAmountOrAPrice(): void
    {
        $price = Money::ofDecimal('19.95', Currency::of('CAD'));

        self::assertSame(['@type' => 'MonetaryAmount', 'currency' => 'CAD', 'value' => '19.95'], new MoneyExtension()->schema($price));
        self::assertSame(['price' => '19.95', 'priceCurrency' => 'CAD'], new MoneyExtension()->price($price));
        self::assertSame([null, null], [new MoneyExtension()->schema(null), new MoneyExtension()->price(null)]);
    }

    public function testTheFormattersOfSixtyFourLocalesAreKeptThenBuiltAgain(): void
    {
        $extension = new MoneyExtension();
        $money = Money::of(100, Currency::of('CAD'));
        $locales = array_map(static fn (int $number): string => 'en_'.$number, range(1, 64));

        foreach ($locales as $locale) {
            $extension->formatMoney($money, $locale);
        }

        self::assertSame($locales, array_keys($this->kept($extension)));

        $first = $this->kept($extension)['en_1'];
        $extension->formatMoney($money, 'en_1');
        self::assertSame($first, $this->kept($extension)['en_1']);
        self::assertCount(64, $this->kept($extension));

        $extension->formatMoney($money, 'en_65');
        self::assertSame(['en_65'], array_keys($this->kept($extension)));
    }

    /**
     * @return array<string, NumberFormatter>
     */
    private function kept(MoneyExtension $extension): array
    {
        /** @var array<string, NumberFormatter> */
        return new ReflectionProperty(MoneyExtension::class, 'formatters')->getValue($extension);
    }
}
