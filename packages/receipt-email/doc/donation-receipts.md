# Donation receipts

An official donation receipt is a document of its own, never a payment receipt: a `Donation` — the gift as the receipt
states it —, a serial number taken from the series of donation receipts, and the date it was issued.

## What an official receipt states

The Canada Revenue Agency lists what an official donation receipt from a registered charity must contain, on its page
[What information must be on an official donation receipt from a registered charity?](https://www.canada.ca/en/revenue-agency/services/charities-giving/charities/operating-a-registered-charity/issuing-receipts/what-information-must-on-official-donation-receipt-a-registered-charity.html)
(page dated 2018-11-02, read on 2026-11-21). Each item of that list and where the package keeps it:

| The receipt states | Kept in |
|---|---|
| That it is an official receipt for income tax purposes | The templates and the PDF, as their title |
| The charity's name and address, as on file with the Agency | `Donation::$charity` |
| A unique serial number | `DonationReceipt::$serialNumber`, from `DonationReceiptNumbering` |
| The registration number the Agency issued | `Donation::$registrationNumber` |
| Where the receipt was issued: the city, town or municipality | `Donation::$issuedIn` |
| The date, or the year, the gift was received | `Donation::$received`: a `DateTimeImmutable`, or the year as an integer |
| The date the receipt was issued | `DonationReceipt::$issuedAt` |
| The donor's full name, with their middle initial, and address | `Donation::$donor` |
| The amount of the gift | `Donation::$amount` |
| The amount and the description of any advantage the donor received | `Donation::$advantage`, `Donation::$advantageDescription` |
| The eligible amount of the gift | `Donation::eligibleAmount()` |
| The signature of an individual the charity authorises to acknowledge gifts | `Donation::$signatory`; the PDF prints the image of the signature above the name when it is given one |
| The Agency's name and website address | `DonationReceipt::authority()`, `DonationReceipt::AUTHORITY_WEBSITE`: `canada.ca/charities-giving` |

A gift of property also states the date it was received, a brief description of it, and the name and address of its
appraiser when it was appraised: `GiftKind::NonCash`, `Donation::$giftDescription`, `Donation::$appraiser`. Its amount
is its fair market value when it was given. The same page asks that the receipt be readable and hard to alter:
the PDF is the copy to keep.

## The advantage and the eligible amount

The eligible amount is the amount of the gift less the advantage. `Donation::of()` refuses:

- an amount of zero or less;
- an advantage below zero, or as large as the gift or larger: the eligible amount is always above zero;
- an advantage in another currency than the gift;
- an advantage above zero without its description;
- a gift of property without its description, and an appraiser on a gift of cash;
- a year received outside 1900 to 9999, and the texts the receipt prints when they are blank, longer than 500
  characters — 64 for the registration number —, or hold line breaks or control characters.

Whether a gift qualifies for a receipt at all — a threshold on the advantage, the kind of property, the donor — is the
charity's decision, which the package does not make.

## The series

`DonationReceiptNumbering::next()` gives the next serial number of the donation receipts: a series of its own,
sequential and without gaps, never shared with the payment receipts. `DonationReceipts::issue()` takes a number,
builds the receipt and keeps it in the `DonationReceiptRepository`; the application calls it inside the transaction
that keeps the receipt, so that a number taken is never lost:

```php
use Phil\ReceiptEmail\Application\DonationReceipts;
use Phil\ReceiptEmail\Domain\Donation\GiftKind;
use Phil\ReceiptEmail\Domain\ValueObject\Donation;
use Phil\ReceiptEmail\Domain\ValueObject\Party;

$donation = Donation::of(
    Party::of('The Reading Circle', ['8 Library Lane', 'Montréal QC  H2X 2Z9']),
    '123456789 RR 0001',
    'Montréal',
    Party::of('Ada B. Lovelace', ['1 Analytical Road', 'Québec QC  G1R 4P5']),
    new DateTimeImmutable('2026-11-18'),
    GiftKind::Cash,
    Money::ofDecimal('150.00', Currency::of('CAD')),
    Money::ofDecimal('40.00', Currency::of('CAD')),
    'A ticket to the gala dinner',
    null,
    null,
    'Grace Hopper, treasurer',
);

$receipt = new DonationReceipts($numbering, $repository)->issue($donation, $clock->now());
$bus->dispatch(new SendDonationReceipt($receipt->serialNumber, attachPdf: true));
```

A gift is checked by `Donation::of()` before the number is taken: a refused gift never uses one.

## The PDF

`Infrastructure\Pdf\PagedMediaDonationReceiptPdf` prints every statement above with `phil/paged-media`, on Letter
unless it is given another page size, over more pages when the addresses and descriptions need them. Its labels are
English, or French for a French locale (`fr`, `fr_CA`); an application replaces any of them by locale or by language:

```php
use Phil\PagedMedia\Image\Image;
use Phil\ReceiptEmail\Infrastructure\Pdf\PagedMediaDonationReceiptPdf;

$pdf = new PagedMediaDonationReceiptPdf(
    labels: ['en' => ['donor' => 'Donor (name and address)']],
    signature: Image::fromFile('/path/to/signature.png'),
);
```

Helvetica prints the Western languages; names in another script need TrueType fonts, which a receipt archived as
PDF/A-2u (`Conformance`) needs too. `ReceiptEmails::donation($serialNumber, attachPdf: true)` attaches it as
`donation-receipt-<serial number>.pdf`.
