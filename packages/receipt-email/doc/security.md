# Security

## What the package protects against

- **Tracking and remote content in a receipt.** A site may edit its templates; `TransactionalCheck` refuses any
  rendered receipt that loads something — an image, a style sheet, a frame, a script, media — or links with a tracking
  parameter, before the email is built. Images are embedded and shown as `cid:` only.
- **Personal data on a message transport.** The Messenger messages carry a receipt's number only, checked as visible
  ASCII; the worker reads the receipt and its recipient again, and the handlers send through a Mailer transport rather
  than putting the email on a Messenger transport.
- **Amounts that differ from the receipt.** Every amount is copied from the receipt as its issuer issued it, in its
  currency; a value of another currency is refused, and the package computes no total.
- **An eligible amount of zero or below on an official donation receipt.** `Donation::of()` refuses an advantage as
  large as the gift, and an advantage without its description.
- **Header and file name injection.** A number is visible ASCII; the PDF's file name keeps letters, digits, `.`, `_`
  and `-` only; the texts a receipt prints hold no line break or control character.
- **Unbounded input.** At most 500 lines, charges and taxes on a receipt, 20 taxes and options on a line, 10 address
  lines, 500 characters a text, 2 MiB of HTML checked.

## What is left to the application

- Who receives a receipt: `RecipientDirectory` gives the address, and the application checks it belongs to the buyer
  or the donor.
- The numbering of the donation receipts, sequential and without gaps, inside the transaction that keeps them, and
  their keeping for the years the law asks.
- Whether a gift qualifies for an official receipt.
- Sending through a transport that authenticates the sender's domain.
