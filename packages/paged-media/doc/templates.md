# Printed templates

The library draws common printed matter from data with the native writer: name badges, address, product and shelf
labels, gift cards, download cards, calendars and large-print editions. Each template is a class built with its
settings, whose `render()` takes a list of items and returns a `Document`, which the application may still draw on
before it renders it.

```php
use Phil\PagedMedia\Template\Badge;
use Phil\PagedMedia\Template\NameBadges;

$document = new NameBadges('Spring Convention 2027')->render([
    new Badge('Élise Tremblay', 'she/her', 'The Gnomes'),
    new Badge('Sam Okafor', organisation: 'Dice Club'),
]);

file_put_contents('badges.pdf', $document->render());
```

The templates print values as they are given: a price, an amount or a date is formatted by the application, in the
reader's language and currency, and so are the words around the values — a gift card's "Code" and "Valid until", a
calendar's month and weekday names. A barcode or a QR code is an `Image` the application draws and passes; the
library does not encode them.

## Sheets

The labels and cards print on sheets, described by a `LabelSheet`: the page, the columns and rows, each label's size,
the margins from the top left corner and the gaps between labels, in points. The items fill the sheet row by row from
the top left label, and a new page starts when one is full.

| Sheet | What it is |
|---|---|
| `LabelSheet::letter30()` | Thirty address labels of 2⅝ × 1 inch on Letter, three columns of ten |
| `LabelSheet::letter10()` | Ten cards of 3½ × 2 inches on Letter, two columns of five: business-card stock |
| `LabelSheet::letter6()` | Six badges of 4 × 3 inches on Letter, two columns of three |
| `LabelSheet::a4x21()` | Twenty-one labels of 70 × 42.3 mm on A4, three columns of seven |
| `LabelSheet::a4x8()` | Eight cards of 105 × 74 mm on A4, two columns of four |
| `LabelSheet::single($page)` | One label filling a page of its own size, such as card stock cut to size |
| `LabelSheet::of(...)` | Any other grid; labels that do not fit on the page are refused |

## Fonts, metadata and conformance

Every template takes `TemplateFonts` — a regular and a bold font, Helvetica and Helvetica Bold by default — then the
document's `Metadata` and an optional `Conformance`. A document that conforms to PDF/A-2u needs TrueType fonts, which
must also hold the ellipsis (…) that ends a text cut to fit.

A line of text is made smaller, by half points down to a floor, until it fits its width, then cut with an ellipsis;
a paragraph is wrapped to its width and cut after the lines its place holds.

## The templates

| Template | Items | What it prints |
|---|---|---|
| `NameBadges($event, $sheet, $fonts, $accent, $logo)` | `Badge($name, $subtitle, $organisation)` | A band with the event's name and logo, the name as large as the badge allows, then a subtitle — pronouns, a role — and an organisation; `letter6()` by default |
| `AddressLabels($sheet, $fonts, $size)` | `AddressLabel($lines)` | The lines centred in the label, the first in bold, empty lines left out, as many as the label holds; `letter30()` by default |
| `ProductLabels($sheet, $fonts)` | `ProductLabel($name, $price, $sku, $detail, $code)` | The name on two lines at most, a detail, the price in bold with the SKU, the code's image on the right; `letter30()` by default |
| `ShelfLabels($sheet, $fonts)` | `ShelfLabel($name, $price, $unitPrice, $sku, $location, $code)` | The name across the top, the price large on the right with the unit price under it, the SKU and location at the foot, the code's image at the bottom left; `letter10()` by default |
| `GiftCards($title, $sheet, $fonts, $logo, $codeLabel, $pinLabel, $validUntilLabel, $cutLines)` | `GiftCard($amount, $code, $pin, $recipient, $message, $validUntil, $codeImage)` | The title and logo, the amount large, the recipient and message, the code, PIN and date of validity at the foot, the code's image on the right, a thin cut line; `letter10()` by default |
| `DownloadCards($instructions, $address, $sheet, $fonts, $logo, $cutLines)` | `DownloadCard($title, $code, $subtitle, $codeImage)` | One card per code: the title and subtitle, how and where to redeem it, the code in a box, the image to scan; `letter10()` by default |
| `Calendar($weekdays, $firstDay, $page, $fonts, $accent)` | `CalendarMonth($year, $month, $title, $events)` | A month per page, landscape Letter by default: the title, the weekday names, the weeks from the first day asked (1 for Monday to 7 for Sunday), each day with its number and its events, cut to what its square shows |
| `LargePrint($page, $fonts, $size, $headingSize, $margin, $pageNumbers)` | `LargePrintBlock::heading()`, `LargePrintBlock::paragraph()` | 18 points or more, left aligned, a line and a half apart, wide margins, headings kept with the two lines that follow, the page number at the foot |

A gift card or a download card on a sheet whose card is too small for all it holds keeps its foot — the code first —
and leaves out the recipient and the message rather than print them over it.

## Checks

Each item checks itself when it is built: a badge has a name, an address a line, a product or shelf label a name and a
price, a gift card an amount and a code, a download card a title and a code; a calendar month is a real month whose
events fall on its days; a calendar names seven days and starts its weeks on one of them; large print is 18 points or
more with headings no smaller, and margins of 18 points or more that leave room for text. Each refusal is an
`InvalidDocument`.

## In Symfony

`phil/paged-media-bundle` registers every template as a document template, rendered from parameters by `PagedMedia`
or in a worker by a message, so the codes of a gift card or a download card travel in the job store, never in the
queue.
