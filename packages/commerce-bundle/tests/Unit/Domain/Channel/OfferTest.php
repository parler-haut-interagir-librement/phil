<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Channel;

use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Offer::class)]
final class OfferTest extends TestCase
{
    public function testAFullOfferIsListedOrderableAndPriced(): void
    {
        self::assertSame([Visibility::Listed, true, true], $this->parts(Offer::full()));
        self::assertSame([Visibility::Listed, true, true], $this->parts(Offer::of(Visibility::Listed)));
        self::assertTrue(Offer::full()->isListed());
        self::assertTrue(Offer::full()->isReachable());
    }

    public function testADirectLinkOfferIsReachableButNotListed(): void
    {
        $offer = Offer::of(Visibility::Unlisted);

        self::assertSame([Visibility::Unlisted, true, true], $this->parts($offer));
        self::assertFalse($offer->isListed());
        self::assertTrue($offer->isReachable());
    }

    public function testAHiddenOfferIsNeitherOrderableNorPriced(): void
    {
        self::assertSame([Visibility::Hidden, false, false], $this->parts(Offer::hidden()));
        self::assertSame([Visibility::Hidden, false, false], $this->parts(Offer::of(Visibility::Hidden)));
        self::assertFalse(Offer::hidden()->isReachable());
        self::assertFalse(Offer::hidden()->isListed());
    }

    public function testAnAnnouncementShowsNeitherThePriceNorTheButton(): void
    {
        self::assertSame([Visibility::Listed, false, false], $this->parts(Offer::of(Visibility::Listed, false, false)));
        self::assertSame([Visibility::Listed, false, true], $this->parts(Offer::of(Visibility::Listed, false)));
    }

    public function testAnOrderableOfferShowsItsPrice(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs('An offer an order may hold shows its price.');

        Offer::of(Visibility::Unlisted, true, false);
    }

    /**
     * @return iterable<string, array{Offer, Offer, array{Visibility, bool, bool}}>
     */
    public static function pairs(): iterable
    {
        yield 'full and full' => [Offer::full(), Offer::full(), [Visibility::Listed, true, true]];
        yield 'full and unlisted' => [Offer::full(), Offer::of(Visibility::Unlisted), [Visibility::Unlisted, true, true]];
        yield 'not orderable and full' => [Offer::of(Visibility::Listed, false), Offer::full(), [Visibility::Listed, false, true]];
        yield 'full and not orderable' => [Offer::full(), Offer::of(Visibility::Unlisted, false), [Visibility::Unlisted, false, true]];
        yield 'unpriced and full' => [Offer::of(Visibility::Listed, false, false), Offer::full(), [Visibility::Listed, false, false]];
        yield 'full and unpriced' => [Offer::full(), Offer::of(Visibility::Listed, false, false), [Visibility::Listed, false, false]];
        yield 'full and hidden' => [Offer::full(), Offer::hidden(), [Visibility::Hidden, false, false]];
        yield 'hidden and full' => [Offer::hidden(), Offer::full(), [Visibility::Hidden, false, false]];
    }

    /**
     * @param array{Visibility, bool, bool} $expected
     */
    #[DataProvider('pairs')]
    public function testTheStricterOfTwoWins(Offer $one, Offer $other, array $expected): void
    {
        self::assertSame($expected, $this->parts($one->stricter($other)));
    }

    /**
     * @return array{Visibility, bool, bool}
     */
    private function parts(Offer $offer): array
    {
        return [$offer->visibility, $offer->orderable, $offer->priceShown];
    }
}
