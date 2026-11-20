<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceWholesaleBundle\Application\GroupPrices;
use Phil\CommerceWholesaleBundle\Application\QuantityRules;
use Phil\CommerceWholesaleBundle\Domain\Exception\QuantityRefused;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\AllowedQuantityValidator;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\QuantityGuard;
use Phil\CommerceWholesaleBundle\Tests\Unit\Support\InMemoryGroups;
use Phil\CommerceWholesaleBundle\Tests\Unit\Support\InMemoryQuantityRules;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Stringable;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The quantity rules checked by the core's order modifier, through the guard asked before every price.
 */
#[CoversClass(QuantityGuard::class)]
#[CoversClass(QuantityRefused::class)]
final class QuantityGuardTest extends TestCase
{
    private OrderModifier $modifier;

    private Order $order;

    #[Override]
    protected function setUp(): void
    {
        $rules = new QuantityRules(new InMemoryQuantityRules(
            QuantityRule::of('DICE', 'wholesale', 6, 60, 6),
            QuantityRule::of('DICE', null, 1, 10),
        ));
        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([AllowedQuantityValidator::class => new AllowedQuantityValidator($rules)]))
            ->getValidator();
        $groups = new InMemoryGroups(CustomerGroup::of('wholesale', 'Wholesale', Currency::of('CAD'), 250_000));

        $this->modifier = new OrderModifier([$this->resolver()], [new QuantityGuard($validator), new GroupPrices($groups)], []);
        $this->order = Order::open('1', Currency::of('CAD'));
    }

    public function testAnAllowedQuantityIsPricedByTheResolversAfterIt(): void
    {
        $this->modifier->changeAudience($this->order, null, 'wholesale', $this->context());
        $this->modifier->add($this->order, SellableReference::of('product', 'DICE'), 6, $this->context());
        $this->modifier->add($this->order, SellableReference::of('product', 'DICE'), 6, $this->context());
        $this->modifier->add($this->order, SellableReference::of('offer', 'DICE'), 7, $this->context());

        self::assertSame([12, 750], [$this->order->lines()[0]->quantity, $this->order->lines()[0]->unitPrice->minorUnits]);
        self::assertSame(7, $this->order->lines()[1]->quantity);
    }

    public function testTheWholeQuantityOfTheLineIsChecked(): void
    {
        $this->modifier->changeAudience($this->order, null, 'wholesale', $this->context());
        $this->modifier->add($this->order, SellableReference::of('product', 'DICE'), 6, $this->context());

        try {
            $this->modifier->add($this->order, SellableReference::of('product', 'DICE'), 1, $this->context());
            self::fail('7 units are between two steps.');
        } catch (QuantityRefused $quantityRefused) {
            self::assertSame(['DICE', 7, ['phil_commerce_wholesale.quantity.off_increment']], [$quantityRefused->sku, $quantityRefused->quantity, $quantityRefused->messages]);
            self::assertSame('7 units of DICE are refused: phil_commerce_wholesale.quantity.off_increment', $quantityRefused->getMessage());
        }

        self::assertSame(6, $this->order->lines()[0]->quantity);
    }

    public function testAChangeOfQuantityIsChecked(): void
    {
        $this->modifier->add($this->order, SellableReference::of('product', 'DICE'), 10, $this->context());

        $this->expectException(QuantityRefused::class);
        $this->expectExceptionMessageIsOrContains('11 units of DICE are refused: phil_commerce_wholesale.quantity.above_maximum');

        $this->modifier->changeQuantity($this->order, SellableReference::of('product', 'DICE'), 11, $this->context());
    }

    public function testAChangeOfGroupChecksTheLinesAgain(): void
    {
        $this->modifier->add($this->order, SellableReference::of('product', 'DICE'), 3, $this->context());

        $this->expectException(QuantityRefused::class);
        $this->expectExceptionMessageIsOrContains('3 units of DICE are refused: phil_commerce_wholesale.quantity.below_minimum');

        $this->modifier->changeAudience($this->order, null, 'wholesale', $this->context());
    }

    /**
     * A violation may carry a Stringable message, as a translatable one; the buyer receives its text.
     */
    public function testAStringableMessageIsGivenAsText(): void
    {
        $message = new class implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return 'Six at a time.';
            }
        };
        $validator = self::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList([new ConstraintViolation($message, null, [], null, '', 7)]));

        try {
            new QuantityGuard($validator)->priceFor(Sellable::of(SellableReference::of('product', 'DICE'), 'DICE', Money::of(1_000, Currency::of('CAD')), 'goods'), PricingContext::of(7, new DateTimeImmutable('2026-11-11 10:00')));
            self::fail('The violation refuses the quantity.');
        } catch (QuantityRefused $quantityRefused) {
            self::assertSame(['Six at a time.'], $quantityRefused->messages);
        }
    }

    private function context(): PricingContext
    {
        return PricingContext::of(1, new DateTimeImmutable('2026-11-11 10:00'));
    }

    private function resolver(): SellableResolver
    {
        return new class implements SellableResolver {
            #[Override]
            public function supports(string $type): bool
            {
                return true;
            }

            #[Override]
            public function resolve(SellableReference $reference): Sellable
            {
                return Sellable::of($reference, $reference->id, Money::of(1_000, Currency::of('CAD')), 'goods');
            }
        };
    }
}
