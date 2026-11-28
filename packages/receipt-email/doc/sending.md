# Sending

## The email

`ReceiptEmails` builds the email of a receipt from its number:

1. it reads the receipt again — `PaymentReceiptSource::find()`, `DonationReceiptRepository::find()` — and its recipient
   — `RecipientDirectory` —, and throws `ReceiptNotFound` when either is missing;
2. it turns them into a `ReceiptMessage` and has the `ReceiptRenderer` render it;
3. `TransactionalCheck` checks the result, and throws `NotTransactional` when it is not a transactional message;
4. it returns a Symfony Mime `Email`: the sender given to its constructor, the recipient, the subject, the plain-text
   and the HTML parts, each image embedded inline under its `cid:` name, the header
   `Auto-Submitted: auto-generated` so that an auto-responder does not answer it, and no `List-Unsubscribe`, a receipt
   being no bulk mail;
5. when the caller asks, it attaches the PDF from `PaymentReceiptPdf` or `DonationReceiptPdf`, and throws
   `PdfUnavailable` when none is given.

```php
$email = $emails->payment('R-000042');                       // the email alone
$email = $emails->payment('R-000042', attachPdf: true);      // with receipt-R-000042.pdf
$email = $emails->donation('D-2026-000017', attachPdf: true);
```

## The transactional checks

`TransactionalCheck::check()` reads the HTML with PHP's HTML5 parser, at most 2 MiB of it, and refuses a rendered
receipt that:

- has a blank plain-text part;
- holds a `script`, `iframe`, `frame`, `object`, `embed`, `link`, `audio`, `video`, `source`, `track`, `picture`, an
  image button, a `meta` refresh, or a `srcset`;
- shows an image, a `background` or a CSS `url()` that is not `cid:` — a tracking pixel, a remote logo, a `data:`
  image —, or imports a style sheet;
- links to an address whose query carries a campaign or click-tracking parameter: `utm_*`, `gclid`, `gbraid`,
  `wbraid`, `dclid`, `fbclid`, `msclkid`, `twclid`, `ttclid`, `li_fat_id`, `mc_cid`, `mc_eid`, `_hsenc`, `_hsmi`,
  `__hssc`, `__hstc`, `__hsfp`, `hsctatracking`, `mkt_tok`, `vero_id`, `vero_conv`, `oly_anon_id`, `oly_enc_id`, `_kx`,
  `ml_subscriber`, `ml_subscriber_hash`, `ck_subscriber_id`, `trk`, `trkid`, `ref_id`, `s_cid`, `icid`, `wickedid`,
  `yclid`, `_openstat`, `igshid` or `srsltid`, in any case, percent-encoded or not, after `&` or `;`. What follows `#`
  is never sent and is not read.

A link to the order, the help page or the shop stays: the check refuses tracking, not links.

## Messenger

`SendPaymentReceipt` carries a receipt's number and `SendDonationReceipt` a serial number, each with whether to attach
the PDF, and nothing else: no address, no name, no amount. Each refuses anything but 1 to 64 visible ASCII characters
— 32 for a serial number —, so that a personal text is not carried by mistake. Route them to an asynchronous
transport:

```php
$bus->dispatch(new SendPaymentReceipt($receipt->number));
$bus->dispatch(new SendDonationReceipt($donationReceipt->serialNumber, attachPdf: true));
```

In the worker, `SendPaymentReceiptHandler` and `SendDonationReceiptHandler` build the email with `ReceiptEmails` and
send it through a Symfony Mailer `TransportInterface` — `mailer.transports` in a Symfony application —, never through
`MailerInterface`: when `SendEmailMessage` is routed asynchronously, `MailerInterface` would put the email, with the
recipient's address and the receipt, back onto a Messenger transport. A receipt not found when the worker runs throws
`ReceiptNotFound`, which Messenger retries and then sends to its failure transport.
