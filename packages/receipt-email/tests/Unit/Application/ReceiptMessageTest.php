<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\ReceiptEmail\Application\ReceiptMessage;
use Phil\ReceiptEmail\Application\Recipient;
use Phil\ReceiptEmail\Infrastructure\Commerce\CommercePaymentReceipts;
use Phil\ReceiptEmail\Tests\Support\Commerce;
use Phil\ReceiptEmail\Tests\Support\Donations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;

#[CoversClass(ReceiptMessage::class)]
final class ReceiptMessageTest extends TestCase
{
    public function testAPaymentReceiptIsCopiedAsIssued(): void
    {
        $message = ReceiptMessage::payment(CommercePaymentReceipts::read(Commerce::receipt()), new Recipient(new Address('ada@example.com', 'Ada'), 'fr_CA'));

        self::assertSame('receipt', $message->template);
        self::assertSame('fr_CA', $message->locale);
        $variables = $message->variables;
        self::assertSame(['name' => 'Ada'], $variables['recipient']);
        self::assertEquals([
            'number' => 'R-000042',
            'document' => 'receipt',
            'order' => 'A-1001',
            'credits' => null,
            'issued_at' => new DateTimeImmutable('2026-11-20 14:30:00'),
            'currency' => 'CAD',
            'subtotal' => '45.00',
            'total' => '56.74',
            'included_total' => null,
        ], $variables['receipt']);
        self::assertSame([
            'name' => 'The Shop',
            'address' => ['12 Main Street', 'Montréal QC  H2X 1Y4'],
            'tax_numbers' => ['GST 123456789 RT0001', 'QST 1234567890 TQ0001'],
        ], $variables['issuer']);
        self::assertSame([
            ['description' => 'Tea', 'quantity' => 3, 'unit_price' => '5.00', 'amount' => '15.00', 'taxes' => ['GST 0.75', 'QST 1.50'], 'options' => [], 'pending' => []],
            ['description' => 'Teapot', 'quantity' => 1, 'unit_price' => '30.00', 'amount' => '30.00', 'taxes' => ['GST 1.50', 'QST 2.99'], 'options' => ['Colour: Blue'], 'pending' => ['Glaze']],
        ], $variables['lines']);
        self::assertSame([['label' => 'Delivery', 'amount' => '10.00'], ['label' => 'Welcome', 'amount' => '-5.00']], $variables['charges']);
        self::assertSame([['code' => 'GST', 'amount' => '2.25'], ['code' => 'QST', 'amount' => '4.49']], $variables['taxes']);
        self::assertSame([], $variables['included_taxes']);
    }

    public function testTaxesIncludedInThePricesAreListedApart(): void
    {
        $variables = ReceiptMessage::paymentVariables(CommercePaymentReceipts::read(Commerce::receipt(taxIncluded: true)), new Recipient(new Address('ada@example.com'), 'en'));

        self::assertSame([], $variables['taxes']);
        self::assertSame([['code' => 'GST', 'amount' => '2.25'], ['code' => 'QST', 'amount' => '4.49']], $variables['included_taxes']);
        self::assertSame('6.74', $variables['receipt']['included_total']);
        self::assertSame('50.00', $variables['receipt']['total']);
        self::assertNull($variables['recipient']['name']);
    }

    public function testACreditNoteIsNegative(): void
    {
        $variables = ReceiptMessage::paymentVariables(CommercePaymentReceipts::read(Commerce::creditNote()), new Recipient(new Address('ada@example.com'), 'en'));

        self::assertSame('credit_note', $variables['receipt']['document']);
        self::assertSame('C-000007', $variables['receipt']['number']);
        self::assertSame('R-000042', $variables['receipt']['credits']);
        self::assertSame('-56.74', $variables['receipt']['total']);
        self::assertSame('-15.00', $variables['lines'][0]['amount']);
        self::assertSame('5.00', $variables['lines'][0]['unit_price']);
        self::assertSame(['GST -0.75', 'QST -1.50'], $variables['lines'][0]['taxes']);
    }

    public function testATemplateMayBeChosen(): void
    {
        $recipient = new Recipient(new Address('ada@example.com'), 'en');

        self::assertSame('shop.receipt', ReceiptMessage::payment(CommercePaymentReceipts::read(Commerce::receipt()), $recipient, 'shop.receipt')->template);
        self::assertSame('gala.receipt', ReceiptMessage::donation(Donations::receipt(), $recipient, 'gala.receipt')->template);
    }

    public function testADonationReceiptStatesTheEligibleAmount(): void
    {
        $message = ReceiptMessage::donation(Donations::receipt(), new Recipient(new Address('ada@example.com', 'Ada'), 'en'));

        self::assertSame('donation-receipt', $message->template);
        self::assertSame('en', $message->locale);
        self::assertEquals([
            'recipient' => ['name' => 'Ada'],
            'receipt' => [
                'serial_number' => 'D-2026-000017',
                'issued_at' => new DateTimeImmutable('2026-11-20 10:00:00'),
                'issued_in' => 'Montréal',
                'received_on' => new DateTimeImmutable('2026-11-18'),
                'received_year' => 2026,
                'kind' => 'cash',
                'currency' => 'CAD',
                'amount' => '150.00',
                'advantage' => '40.00',
                'advantage_description' => 'A ticket to the gala dinner',
                'eligible_amount' => '110.00',
                'gift_description' => null,
                'signatory' => 'Grace Hopper, treasurer',
            ],
            'charity' => ['name' => 'The Reading Circle', 'address' => ['8 Library Lane', 'Montréal QC  H2X 2Z9'], 'registration_number' => '123456789 RR 0001'],
            'donor' => ['name' => 'Ada B. Lovelace', 'address' => ['1 Analytical Road', 'Québec QC  G1R 4P5']],
            'appraiser' => null,
            'authority' => ['name' => 'Canada Revenue Agency', 'website' => 'canada.ca/charities-giving'],
        ], $message->variables);
    }

    public function testAGiftOfPropertyNamesItsAppraiserAndItsYear(): void
    {
        $variables = ReceiptMessage::donationVariables(Donations::receipt(Donations::property()), new Recipient(new Address('ada@example.com'), 'fr'));

        self::assertNull($variables['receipt']['received_on']);
        self::assertSame(2026, $variables['receipt']['received_year']);
        self::assertSame('non_cash', $variables['receipt']['kind']);
        self::assertSame('0.00', $variables['receipt']['advantage']);
        self::assertSame('1200.00', $variables['receipt']['eligible_amount']);
        self::assertSame(['name' => 'Charles Babbage, appraiser', 'address' => ['2 Difference Street', 'Laval QC  H7N 1A1']], $variables['appraiser']);
        self::assertSame('Agence du revenu du Canada', $variables['authority']['name']);
    }

    public function testTheYearOfADateIsGivenToo(): void
    {
        $variables = ReceiptMessage::donationVariables(Donations::receipt(Donations::cash(received: new DateTimeImmutable('2025-12-31'))), new Recipient(new Address('ada@example.com'), 'en'));

        self::assertSame(2025, $variables['receipt']['received_year']);
    }
}
