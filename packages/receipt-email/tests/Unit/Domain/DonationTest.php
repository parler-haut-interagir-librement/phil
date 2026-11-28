<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Donation\GiftKind;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\Donation;
use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\Party;
use Phil\ReceiptEmail\Tests\Support\Donations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Donation::class)]
#[CoversClass(DonationReceipt::class)]
final class DonationTest extends TestCase
{
    public function testTheEligibleAmountIsTheGiftLessTheAdvantage(): void
    {
        $donation = Donations::cash(15_000, 4_000);

        self::assertSame(15_000, $donation->amount->minorUnits);
        self::assertSame(4_000, $donation->advantage->minorUnits);
        self::assertSame(11_000, $donation->eligibleAmount()->minorUnits);
        self::assertSame('A ticket to the gala dinner', $donation->advantageDescription);
        self::assertSame(15_000, Donations::cash(15_000, 0)->eligibleAmount()->minorUnits);
        self::assertSame(1, Donations::cash(15_000, 14_999)->eligibleAmount()->minorUnits);
    }

    public function testAGiftKeepsEveryStatementOfTheReceipt(): void
    {
        $donation = Donations::cash();

        self::assertSame('The Reading Circle', $donation->charity->name);
        self::assertSame('123456789 RR 0001', $donation->registrationNumber);
        self::assertSame('Montréal', $donation->issuedIn);
        self::assertSame('Ada B. Lovelace', $donation->donor->name);
        self::assertInstanceOf(DateTimeImmutable::class, $donation->received);
        self::assertSame(GiftKind::Cash, $donation->kind);
        self::assertNull($donation->giftDescription);
        self::assertNull($donation->appraiser);
        self::assertSame('Grace Hopper, treasurer', $donation->signatory);
    }

    public function testNoAdvantageIsZero(): void
    {
        $property = Donations::property();

        self::assertTrue($property->advantage->isZero());
        self::assertSame('CAD', $property->advantage->currency->code);
        self::assertSame(2026, $property->received);
        self::assertSame('A first edition of a book on the analytical engine', $property->giftDescription);
        self::assertSame('Charles Babbage, appraiser', $property->appraiser?->name);
    }

    public function testTheYearAGiftWasReceivedHasFourDigits(): void
    {
        self::assertSame(1900, Donations::cash(received: 1900)->received);
        self::assertSame(9999, Donations::cash(received: 9999)->received);

        foreach ([1899, 10_000] as $year) {
            try {
                Donations::cash(received: $year);
                self::fail(\sprintf('%d is refused.', $year));
            } catch (InvalidReceipt $exception) {
                self::assertSame('The year a gift was received is written with four digits, from 1900.', $exception->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function refusedAmounts(): iterable
    {
        yield 'zero gift' => [0, 0];
        yield 'negative gift' => [-100, 0];
    }

    #[DataProvider('refusedAmounts')]
    public function testAGiftIsAboveZero(int $amount, int $advantage): void
    {
        $this->expectException(InvalidReceipt::class);
        $this->expectExceptionMessageIs('The amount of a gift is above zero.');

        Donations::cash($amount, $advantage);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedAdvantages(): iterable
    {
        yield 'negative' => [-1];
        yield 'as much as the gift' => [15_000];
        yield 'more than the gift' => [15_001];
    }

    #[DataProvider('refusedAdvantages')]
    public function testTheAdvantageLeavesAnEligibleAmount(int $advantage): void
    {
        $this->expectExceptionMessageIs('The advantage is zero or more, and less than the amount of the gift: the eligible amount is above zero.');

        Donations::cash(15_000, $advantage);
    }

    public function testTheAdvantageIsInTheGiftsCurrency(): void
    {
        $this->expectExceptionMessageIs('The advantage is in USD, and the receipt in CAD.');

        $this->donation(advantage: Money::of(100, Currency::of('USD')), advantageDescription: 'A mug');
    }

    public function testAnAdvantageIsDescribed(): void
    {
        $this->expectExceptionMessageIs('An advantage is described on the receipt.');

        $this->donation(advantage: $this->cad(100));
    }

    public function testADescriptionIsAText(): void
    {
        foreach (['advantageDescription' => 'The description of the advantage', 'giftDescription' => 'The description of the gift'] as $argument => $field) {
            try {
                $this->donation(...[$argument => ' ']);
                self::fail($field.' is checked.');
            } catch (InvalidReceipt $exception) {
                self::assertStringStartsWith($field.' is a text', $exception->getMessage());
            }
        }

        self::assertSame('A pen', $this->donation(advantageDescription: 'A pen')->advantageDescription);
        self::assertSame('Books', $this->donation(giftDescription: 'Books')->giftDescription);
    }

    public function testAGiftOfPropertyIsDescribed(): void
    {
        self::assertSame(GiftKind::NonCash, $this->donation(GiftKind::NonCash, giftDescription: 'Books')->kind);

        $this->expectExceptionMessageIs('A gift of property is described on the receipt.');

        $this->donation(GiftKind::NonCash);
    }

    public function testAGiftOfCashHasNoAppraiser(): void
    {
        self::assertSame('Appraiser', $this->donation(GiftKind::NonCash, giftDescription: 'Books', appraiser: Party::of('Appraiser'))->appraiser?->name);

        $this->expectExceptionMessageIs('A gift of cash has no appraiser.');

        $this->donation(appraiser: Party::of('Appraiser'));
    }

    public function testTheTextsOfTheReceiptAreChecked(): void
    {
        foreach (['registrationNumber' => 'The registration number of the charity', 'issuedIn' => 'Where the receipt is issued', 'signatory' => 'The signatory'] as $argument => $field) {
            try {
                $this->donation(...[$argument => '']);
                self::fail($field.' is checked.');
            } catch (InvalidReceipt $exception) {
                self::assertStringStartsWith($field.' is a text', $exception->getMessage());
            }
        }

        $this->expectExceptionMessageIs('The registration number of the charity is a text of 1 to 64 characters, without line breaks or control characters.');

        $this->donation(registrationNumber: str_repeat('1', 65));
    }

    public function testAReceiptHasASerialNumberOfItsOwnSeries(): void
    {
        $receipt = DonationReceipt::of(str_repeat('D', 32), new DateTimeImmutable('2026-11-20'), Donations::cash());

        self::assertSame(str_repeat('D', 32), $receipt->serialNumber);
        self::assertSame('2026-11-20', $receipt->issuedAt->format('Y-m-d'));
        self::assertSame(11_000, $receipt->donation->eligibleAmount()->minorUnits);

        $this->expectExceptionMessageIs('The serial number of a donation receipt is 1 to 32 visible ASCII characters.');

        DonationReceipt::of(str_repeat('D', 33), new DateTimeImmutable('2026-11-20'), Donations::cash());
    }

    public function testTheAuthorityIsNamedInTheLanguageOfTheReceipt(): void
    {
        self::assertSame('Canada Revenue Agency', DonationReceipt::authority('en'));
        self::assertSame('Canada Revenue Agency', DonationReceipt::authority('en_CA'));
        self::assertSame('Canada Revenue Agency', DonationReceipt::authority('fra'));
        self::assertSame('Canada Revenue Agency', DonationReceipt::authority('xfr'));
        self::assertSame('Agence du revenu du Canada', DonationReceipt::authority('fr'));
        self::assertSame('Agence du revenu du Canada', DonationReceipt::authority('fr_CA'));
        self::assertSame('Agence du revenu du Canada', DonationReceipt::authority('FR-ca'));
        self::assertSame('canada.ca/charities-giving', DonationReceipt::AUTHORITY_WEBSITE);
    }

    private function donation(
        GiftKind $kind = GiftKind::Cash,
        ?Money $advantage = null,
        ?string $advantageDescription = null,
        ?string $giftDescription = null,
        ?Party $appraiser = null,
        string $registrationNumber = '123456789 RR 0001',
        string $issuedIn = 'Montréal',
        string $signatory = 'Grace Hopper',
    ): Donation {
        return Donation::of(Donations::charity(), $registrationNumber, $issuedIn, Donations::donor(), 2026, $kind, $this->cad(10_000), $advantage, $advantageDescription, $giftDescription, $appraiser, $signatory);
    }

    private function cad(int $minor): Money
    {
        return Money::of($minor, Currency::of('CAD'));
    }
}
