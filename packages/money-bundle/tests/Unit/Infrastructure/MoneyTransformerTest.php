<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\MoneyBundle\Infrastructure\Form\MoneyTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoneyTransformer::class)]
final class MoneyTransformerTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('en');
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testAnAmountIsInTheSubmittedCurrencyUnlessTheCurrencyIsFixed(): void
    {
        $submitted = ['amount' => '12.50', 'currency' => 'USD'];

        self::assertEquals(Money::of(1_250, Currency::of('USD')), new MoneyTransformer(Currency::of('CAD'))->reverseTransform($submitted));
        self::assertEquals(Money::of(1_250, Currency::of('CAD')), new MoneyTransformer(Currency::of('CAD'), true)->reverseTransform($submitted));
    }
}
