<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\ItemCondition;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLabel;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLine;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a return holds beside its lines: the specials it breaks, the delivery it gives back, its label, its slip, the
 * questions and the expiry.
 */
#[CoversClass(ReturnRequest::class)]
#[CoversClass(ReturnLabel::class)]
#[CoversClass(ReturnState::class)]
final class ReturnRequestRulesTest extends TestCase
{
    public function testTheValueTakesTheSpecialsOffAndTheDeliveryOn(): void
    {
        $return = $this->return(['promotion:duo' => 300, 'promotion:trio' => 200], 800);

        self::assertSame(['promotion:duo' => 300, 'promotion:trio' => 200], $return->specialCharges());
        self::assertSame(800, $return->deliveryRefund()->minorUnits);
        self::assertSame('CAD', $return->deliveryRefund()->currency->code);
        // 20.00 less a fee of 10 % on a change of mind, less 5.00 of specials, plus 8.00 of delivery.
        self::assertSame(2_000 - 200 - 500 + 800, $return->value()->minorUnits);
        self::assertSame(200, $return->feeOn($return->lines()[0]));
    }

    public function testTheSpecialsNeverTakeTheLinesBelowZero(): void
    {
        $return = $this->return(['promotion:duo' => 5_000], 800);
        $return->authorize(new DateTimeImmutable());
        $return->receive(['product:BOOK' => ItemCondition::Missing], new DateTimeImmutable());

        self::assertSame(800, $return->value()->minorUnits);
        self::assertSame(0, $this->return([], 0)->deliveryRefund()->minorUnits);
    }

    /**
     * @return iterable<string, array{array<array-key, int>, int, string}>
     */
    public static function invalidCharges(): iterable
    {
        yield 'a charge of zero' => [['promotion:duo' => 0], 0, 'A return of A-1 takes back the discount of a special named by its origin, more than zero.'];
        yield 'an origin with a space' => [['promotion duo' => 10], 0, 'A return of A-1 takes back the discount of a special named by its origin, more than zero.'];
        yield 'an origin with a line break at its end' => [['promotion:duo'."\n" => 10], 0, 'A return of A-1 takes back the discount of a special named by its origin, more than zero.'];
        yield 'a negative delivery' => [[], -1, 'A return of A-1 gives back a delivery charge of zero or more.'];
    }

    /**
     * @param array<string, int> $charges
     */
    #[DataProvider('invalidCharges')]
    public function testInvalidChargesAreRefused(array $charges, int $delivery, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->return($charges, $delivery);
    }

    public function testAQuestionSendsTheReturnBackToTheCustomer(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00');
        $return = $this->return([], 0);
        $return->askForInformation('Which colour?', $at);

        self::assertSame([ReturnState::AwaitingInformation, 'Which colour?', $at], [$return->state(), $return->note(), $return->changedAt()]);

        try {
            $return->authorize($at);
            self::fail('A return waiting for information was authorized.');
        } catch (ReturnRefused $returnRefused) {
            self::assertSame(\sprintf('The return %s cannot go from awaiting_information to authorized.', $return->id), $returnRefused->getMessage());
        }

        $return->provideInformation($at);
        self::assertSame(ReturnState::Requested, $return->state());

        $this->expectException(ReturnRefused::class);

        $return->provideInformation($at);
    }

    public function testAQuestionIsAskedBeforeTheGoodsCome(): void
    {
        $return = $this->return([], 0);
        $return->authorize(new DateTimeImmutable());
        $return->receive(['product:BOOK' => ItemCondition::Resellable], new DateTimeImmutable());

        $this->expectException(ReturnRefused::class);

        $return->askForInformation('Too late?', new DateTimeImmutable());
    }

    public function testALongQuestionIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        $this->return([], 0)->askForInformation(str_repeat('a', 256), new DateTimeImmutable());
    }

    public function testOnlyAnAuthorizedReturnExpires(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00');
        $return = $this->return([], 0);

        try {
            $return->expire($at);
            self::fail('A requested return expired.');
        } catch (ReturnRefused) {
            self::assertSame(ReturnState::Requested, $return->state());
        }

        $return->authorize($at);
        $return->expire($at->modify('+1 day'));
        self::assertSame([ReturnState::Expired, false], [$return->state(), $return->state()->holds()]);
        self::assertEquals($at->modify('+1 day'), $return->changedAt());
    }

    public function testALabelIsKeptOnAnAuthorizedReturn(): void
    {
        $return = $this->return([], 0);
        self::assertNull($return->label());

        try {
            $return->attachLabel(ReturnLabel::of('carrier', 'T-1'));
            self::fail('A requested return got a label.');
        } catch (ReturnRefused) {
            self::assertNull($return->label());
        }

        $return->authorize(new DateTimeImmutable());
        $return->attachLabel(ReturnLabel::of('carrier', 'T-1'));

        $label = $return->label();
        self::assertNotNull($label);
        self::assertSame(['carrier', 'T-1', null], [$label->carrier, $label->tracking, $label->document]);
    }

    public function testAnonymizingErasesTheNote(): void
    {
        $return = $this->return([], 0);
        $return->reject('Opened', new DateTimeImmutable());
        $return->anonymize();

        self::assertNull($return->note());
        self::assertSame(ReturnState::Rejected, $return->state());
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function invalidLabels(): iterable
    {
        yield 'no carrier' => ['', 'T-1', null];
        yield 'a carrier too long' => [str_repeat('c', 65), 'T-1', null];
        yield 'a tracking number with a space' => ['carrier', 'T 1', null];
        yield 'a tracking number too long' => ['carrier', str_repeat('t', 129), null];
        yield 'a carrier with a line break at its end' => ["carrier\n", 'T-1', null];
        yield 'a tracking number with a line break at its end' => ['carrier', "T-1\n", null];
        yield 'a file with a line break at its end' => ['carrier', 'T-1', "file\n"];
        yield 'an empty file' => ['carrier', 'T-1', ''];
        yield 'a file too long' => ['carrier', 'T-1', str_repeat('f', 256)];
    }

    #[DataProvider('invalidLabels')]
    public function testAnInvalidLabelIsRefused(string $carrier, string $tracking, ?string $document): void
    {
        $this->expectException(InvalidValue::class);

        ReturnLabel::of($carrier, $tracking, $document);
    }

    public function testAChargeOfOneUnitIsKept(): void
    {
        self::assertSame(['promotion:duo' => 1], $this->return(['promotion:duo' => 1], 0)->specialCharges());
    }

    public function testALabelAtItsBoundsIsKept(): void
    {
        $label = ReturnLabel::of(str_repeat('c', 64), str_repeat('t', 128), str_repeat('f', 255));

        self::assertEquals($label, ReturnLabel::fromArray($label->toArray()));
    }

    /**
     * @param array<string, int> $charges
     */
    private function return(array $charges, int $delivery): ReturnRequest
    {
        return ReturnRequest::request('A-1', Currency::of('CAD'), [ReturnLine::of('product:BOOK', 1, ReturnReason::ChangedMind, 2_000, 1)], 100_000, new DateTimeImmutable(), $charges, $delivery);
    }
}
