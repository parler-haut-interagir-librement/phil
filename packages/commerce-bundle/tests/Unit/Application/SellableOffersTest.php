<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use ArrayObject;
use Override;
use Phil\CommerceBundle\Application\Port\SellableAvailability;
use Phil\CommerceBundle\Application\SellableOffers;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SellableOffers::class)]
final class SellableOffersTest extends TestCase
{
    public function testWithoutSelectionEverythingIsOfferedInFull(): void
    {
        self::assertEquals(Offer::full(), new SellableOffers()->of(SellableReference::of('product', 'dice'), 'web'));
    }

    public function testTheStrictestSelectionWins(): void
    {
        $asked = new ArrayObject();
        $offers = new SellableOffers([
            $this->selection(Offer::full(), $asked),
            $this->selection(Offer::of(Visibility::Unlisted), $asked),
            $this->selection(Offer::of(Visibility::Listed, false), $asked),
        ]);

        self::assertEquals(Offer::of(Visibility::Unlisted, false), $offers->of(SellableReference::of('product', 'dice'), 'web'));
        self::assertSame(['product:dice web', 'product:dice web', 'product:dice web'], $asked->getArrayCopy());
    }

    public function testAHiddenAnswerStopsTheAsking(): void
    {
        $asked = new ArrayObject();
        $offers = new SellableOffers([
            $this->selection(Offer::of(Visibility::Unlisted), $asked),
            $this->selection(Offer::hidden(), $asked),
            $this->selection(Offer::full(), $asked),
        ]);

        self::assertEquals(Offer::hidden(), $offers->of(SellableReference::of('product', 'dice'), null));
        self::assertSame(['product:dice none', 'product:dice none'], $asked->getArrayCopy());
    }

    public function testTheSelectionsAreAskedWithoutTheLineConfiguration(): void
    {
        $asked = new ArrayObject();
        $reference = SellableReference::of('product', 'shirt', '0123456789abcdef');

        new SellableOffers([$this->selection(Offer::full(), $asked)])->of($reference, 'web');

        self::assertSame(['product:shirt web'], $asked->getArrayCopy());
    }

    /**
     * @param ArrayObject<int, string> $asked
     */
    private function selection(Offer $answer, ArrayObject $asked): SellableAvailability
    {
        return new readonly class($answer, $asked) implements SellableAvailability {
            /** @param ArrayObject<int, string> $asked */
            public function __construct(private Offer $answer, private ArrayObject $asked)
            {
            }

            #[Override]
            public function offerThrough(SellableReference $reference, ?string $channel): Offer
            {
                $this->asked->append($reference->value().' '.($channel ?? 'none'));

                return $this->answer;
            }
        };
    }
}
