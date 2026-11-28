# Templates and rendering

## The message

`ReceiptMessage::payment()` and `ReceiptMessage::donation()` turn a receipt and its recipient into what the email says:
a template key — `receipt` or `donation-receipt`, or one the application names —, the recipient's locale, and the
variables. The variables are the receipt's own values: each amount written as a decimal of its currency by
`Money::toDecimal()` (`56.74`, `-12.50` on a credit note), the dates as `DateTimeImmutable`, the recipient's name or
`null`. No variable carries an offer, a tracking identifier or anything but the receipt.

### A payment receipt

| Variable | Holds |
|---|---|
| `recipient.name` | The name to greet, or `null`. |
| `receipt.number`, `receipt.document`, `receipt.order`, `receipt.credits` | The number, `receipt` or `credit_note`, the order, the credited receipt or `null`. |
| `receipt.issued_at`, `receipt.currency` | The date of issue, the currency code. |
| `receipt.subtotal`, `receipt.total`, `receipt.included_total` | The subtotal, the total, the taxes the prices included or `null`. |
| `issuer.name`, `issuer.address`, `issuer.tax_numbers` | The issuer, its address lines, its registration numbers as `GST 123456789 RT0001`. |
| `lines[].description`, `quantity`, `unit_price`, `amount` | Each line. |
| `lines[].taxes`, `lines[].options`, `lines[].pending` | The line's taxes as `GST 0.75`, its options as `Colour: Blue`, the labels of its options still to draw. |
| `charges[].label`, `charges[].amount` | Shipping, fees, discounts, tips. |
| `taxes[].code`, `taxes[].amount` | Each tax added to the total, by rate code. |
| `included_taxes[].code`, `included_taxes[].amount` | Each tax the prices included. |

A line's taxes and options are lists of texts rather than of objects: `phil/email-templates-bundle` nests a schema at
most three levels deep.

### A donation receipt

| Variable | Holds |
|---|---|
| `recipient.name` | The name to greet, or `null`. |
| `receipt.serial_number`, `receipt.issued_at`, `receipt.issued_in` | The serial number, the date and the place of issue. |
| `receipt.received_on`, `receipt.received_year` | The date the gift was received or `null`, and its year. |
| `receipt.kind` | `cash` or `non_cash`. |
| `receipt.currency`, `receipt.amount`, `receipt.advantage`, `receipt.eligible_amount` | The amounts. |
| `receipt.advantage_description`, `receipt.gift_description`, `receipt.signatory` | The descriptions, or `null`, and the signatory. |
| `charity.name`, `charity.address`, `charity.registration_number` | The charity. |
| `donor.name`, `donor.address` | The donor. |
| `appraiser.name`, `appraiser.address` | The appraiser of a gift of property, or `appraiser` is `null`. |
| `authority.name`, `authority.website` | `Canada Revenue Agency` or `Agence du revenu du Canada` by the locale, and `canada.ca/charities-giving`. |

## The renderer

The `ReceiptRenderer` port renders a message into a `RenderedReceipt`: the subject, the preheader, the HTML, the plain
text and the images embedded as `InlineImage`. `Infrastructure\EmailTemplates\EmailTemplatesRenderer` renders with the
`EmailRenderer` of `phil/email-templates-bundle`: the site's published template of the key and the locale, else the
shared one, else the default file; the locale falls back to its language, then to the default locale. The site is the
current one, or the one given to its constructor. The values are checked against the template's schema, and the
template renders in the bundle's Twig sandbox.

## The default templates

The directory `email-templates/` holds `receipt.en.yaml`, `receipt.fr.yaml`, `donation-receipt.en.yaml` and
`donation-receipt.fr.yaml`. Each declares the schema of the variables above with sample values, writes its HTML body
with inline styles and its own plain-text part, embeds no image and links nowhere. The French templates write the
amounts with a decimal comma. A site changes them as it changes any template of `phil/email-templates-bundle`; its
templates pass through `TransactionalCheck` all the same.

The house layout of `phil/email-templates-bundle` shows `brand.logo` as an image: a logo given as an address is
refused by `TransactionalCheck`, as any remote image is. Leave it out, or give `cid:logo` and declare the image in the
`embeds` of the templates, which attach it inline.
