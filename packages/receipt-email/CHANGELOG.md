# Changelog

All notable changes to `phil/receipt-email` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `PaymentReceipt`, `ReceiptLine`, `ReceiptCharge`, `TaxAmount`, `Party` and `PaymentDocument`: a payment receipt or a
  credit note as its issuer numbered it, every amount a `Money` in one currency, the taxes per line and summed by rate
  code, those the prices included marked, never computed again.
- `Donation`, `DonationReceipt` and `GiftKind`: the official donation receipt, with every statement the Canada Revenue
  Agency lists for a registered charity, the eligible amount as the gift less the advantage, always above zero, and the
  description and appraiser of a gift of property.
- `DonationReceipts`, `DonationReceiptNumbering` and `DonationReceiptRepository`: donation receipts issued in their own
  series of serial numbers, never shared with the payment receipts.
- `ReceiptMessage`: the template key, the locale and the typed variables of a receipt's email, holding nothing but the
  receipt and the recipient's name.
- `ReceiptRenderer`, `RenderedReceipt` and `InlineImage`: the port that renders a message into a subject, a preheader,
  an HTML part, a plain-text part and images embedded as `cid:`.
- `TransactionalCheck`: a rendered receipt refused without a plain-text part, with a remote image, a tracking pixel, a
  script, a frame, a remote style sheet, or a link carrying a tracking parameter.
- `ReceiptEmails` and `Recipient`: the Symfony Mime `Email` of a receipt read again by its number, marked
  `Auto-Submitted: auto-generated`, its PDF attached only when asked for.
- `CommercePaymentReceipts` and `CommerceReceiptPdf`: the receipts and credit notes of `phil/commerce-bundle`, and
  their PDF by locale or language.
- `EmailTemplatesRenderer` and the default templates `receipt` and `donation-receipt`, in English and in French, for
  `phil/email-templates-bundle`.
- `PagedMediaDonationReceiptPdf`: the PDF of a donation receipt with `phil/paged-media`, its labels in English and in
  French, the image of the signature.
- `SendPaymentReceipt`, `SendDonationReceipt` and their handlers: Messenger messages that carry a receipt's number
  only, handled by a worker that sends through a Mailer transport.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
