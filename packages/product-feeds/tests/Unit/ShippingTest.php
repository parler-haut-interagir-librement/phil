<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Tests\Unit;

use Phil\ProductFeeds\Exception\InvalidFeedItem;
use Phil\ProductFeeds\Shipping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Shipping::class)]
final class ShippingTest extends TestCase
{
    public function testAShippingCostHasACountryAPriceAndMaybeAService(): void
    {
        $any = new Shipping('CA', Items::cad(0));
        $express = new Shipping('US', Items::cad(900), "  Express\tovernight ");

        self::assertSame(['CA', 0, null], [$any->country, $any->price->minorUnits, $any->service]);
        self::assertSame(['US', 900, 'Express overnight'], [$express->country, $express->price->minorUnits, $express->service]);
        self::assertNull(new Shipping('CA', Items::cad(1), ' ')->service);
        self::assertSame(str_repeat('é', 100), new Shipping('CA', Items::cad(1), str_repeat('é', 100))->service);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notCountries(): iterable
    {
        yield 'in small letters' => ['ca'];
        yield 'one letter' => ['C'];
        yield 'three letters' => ['CAN'];
        yield 'a trailing line break' => ["CA\n"];
        yield 'a digit' => ['C1'];
        yield 'a letter before' => ['xCA'];
    }

    #[DataProvider('notCountries')]
    public function testTheCountryIsTwoCapitalLetters(string $country): void
    {
        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs(\sprintf('"%s" is not a country: two capital letters of ISO 3166-1.', $country));

        new Shipping($country, Items::cad(1));
    }

    public function testThePriceIsNotNegative(): void
    {
        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The shipping to CA has a negative price.');

        new Shipping('CA', Items::cad(-1));
    }

    public function testTheServiceHoldsAHundredCharacters(): void
    {
        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The shipping service to CA holds 100 characters at most.');

        new Shipping('CA', Items::cad(1), str_repeat('é', 101));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function separators(): iterable
    {
        yield 'a colon' => ['Next: day'];
        yield 'a comma' => ['Fast, tracked'];
    }

    #[DataProvider('separators')]
    public function testTheServiceHoldsNoSeparatorOfTsv(string $service): void
    {
        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The shipping service to CA holds neither a colon nor a comma.');

        new Shipping('CA', Items::cad(1), $service);
    }
}
