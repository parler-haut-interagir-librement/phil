<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Domain;

use Phil\CommerceWholesaleBundle\Domain\Exception\WholesaleRefused;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Domain\Model\Membership;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomerGroup::class)]
#[CoversClass(Membership::class)]
#[CoversClass(WholesaleRefused::class)]
final class CustomerGroupTest extends TestCase
{
    public function testAGroupHoldsItsTerms(): void
    {
        $group = CustomerGroup::of('wholesale', 'Grossistes', Currency::of('CAD'), 250_000, $this->cad(50_000));

        self::assertSame('wholesale', $group->code);
        self::assertSame('Grossistes', $group->name());
        self::assertSame('CAD', $group->currency->code);
        self::assertSame(250_000, $group->discount());
        self::assertSame(50_000, $group->minimumOrder()->minorUnits);
        self::assertTrue($group->isActive());

        $plain = CustomerGroup::of('staff', 'Staff', Currency::of('CAD'));
        self::assertSame(0, $plain->discount());
        self::assertTrue($plain->minimumOrder()->isZero());
        self::assertSame('CAD', $plain->minimumOrder()->currency->code);
    }

    /**
     * 25 % off 19.99 is 14.9925, rounded to 14.99; no share leaves the price as it is.
     */
    public function testTheGroupsPriceIsTheBaseLessItsShare(): void
    {
        self::assertSame(1_499, CustomerGroup::of('w', 'W', Currency::of('CAD'), 250_000)->priceFrom($this->cad(1_999))->minorUnits);
        self::assertSame(1_999, CustomerGroup::of('s', 'S', Currency::of('CAD'))->priceFrom($this->cad(1_999))->minorUnits);
        self::assertSame(1, CustomerGroup::of('x', 'X', Currency::of('CAD'), 999_999)->priceFrom($this->cad(1_000_000))->minorUnits);
    }

    public function testItsTermsAndNameChange(): void
    {
        $group = CustomerGroup::of('w', 'W', Currency::of('CAD'));
        $group->rename('Wholesale');
        $group->changeTerms(100_000, $this->cad(10_000));

        self::assertSame('Wholesale', $group->name());
        self::assertSame([100_000, 10_000], [$group->discount(), $group->minimumOrder()->minorUnits]);

        $group->withdraw();
        self::assertFalse($group->isActive());
        $group->restore();
        self::assertTrue($group->isActive());
    }

    /**
     * @return iterable<string, array{string, string, int, ?int, string, string}>
     */
    public static function invalidGroups(): iterable
    {
        yield 'empty code' => ['', 'W', 0, null, 'CAD', '"" is not a customer group: 1 to 64 visible ASCII characters.'];
        yield 'code with a space' => ['a b', 'W', 0, null, 'CAD', 'is not a customer group'];
        yield 'code too long' => [str_repeat('g', 65), 'W', 0, null, 'CAD', 'is not a customer group'];
        yield 'code with a trailing newline' => ["w\n", 'W', 0, null, 'CAD', 'is not a customer group'];
        yield 'blank name' => ['w', ' ', 0, null, 'CAD', 'The customer group w has a name.'];
        yield 'negative share' => ['w', 'W', -1, null, 'CAD', 'A group takes 0 to 999999 parts per million off; -1 is refused.'];
        yield 'everything off' => ['w', 'W', 1_000_000, null, 'CAD', 'A group takes 0 to 999999 parts per million off; 1000000 is refused.'];
        yield 'negative minimum' => ['w', 'W', 0, -1, 'CAD', 'The minimum order of w is zero or more, in CAD.'];
        yield 'minimum in another currency' => ['w', 'W', 0, 100, 'USD', 'The minimum order of w is zero or more, in CAD.'];
    }

    #[DataProvider('invalidGroups')]
    public function testAnInvalidGroupIsRefused(string $code, string $name, int $discount, ?int $minimum, string $currency, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        CustomerGroup::of($code, $name, Currency::of('CAD'), $discount, null === $minimum ? null : Money::of($minimum, Currency::of($currency)));
    }

    public function testABuyerBelongsToOneGroupAndMoves(): void
    {
        $wholesale = CustomerGroup::of('wholesale', 'W', Currency::of('CAD'));
        $membership = Membership::of('tenant-42', $wholesale);

        self::assertSame('tenant-42', $membership->buyer);
        self::assertSame('wholesale', $membership->group());

        $membership->moveTo(CustomerGroup::of('vip', 'V', Currency::of('CAD')));
        self::assertSame('vip', $membership->group());
        self::assertSame(255, \strlen(Membership::of(str_repeat('b', 255), $wholesale)->buyer));

        foreach ([' ', str_repeat('b', 256)] as $buyer) {
            try {
                Membership::of($buyer, $wholesale);
                self::fail('The buyer was accepted.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame('A buyer is named by 1 to 255 characters.', $invalidValue->getMessage());
            }
        }
    }

    public function testARefusalIsADomainError(): void
    {
        self::assertInstanceOf(DomainError::class, WholesaleRefused::unknownGroup('x'));
        self::assertSame('No customer group is known as "x".', WholesaleRefused::unknownGroup('x')->getMessage());
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
