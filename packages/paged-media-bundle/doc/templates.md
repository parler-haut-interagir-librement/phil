# Printed templates

The bundle registers the printed templates of `phil/paged-media` as document templates: `PagedMedia::renderTemplate()`
renders them at once, and `PagedMediaQueue::render()` in a worker. Their parameters are JSON-like — strings, numbers,
booleans, lists and objects — so they are kept in the job store as they are, and the codes of a gift card or a
download card, the names on badges and the addresses on labels never travel in a message.

```php
$job = $queue->render('paged_media.gift_cards', [
    'card_title' => 'Gift card',
    'cards' => [['amount' => '$50.00', 'code' => 'GC-7F3K-9QPA', 'pin' => '4821', 'valid_until' => 'December 31, 2027']],
], 'gift-cards/batch-12.pdf');
```

Values are printed as they are given: the application formats amounts, prices and dates in the reader's language, and
gives the words around them. Images — a logo, a barcode, a QR code — cannot be passed as parameters: an application
that needs them builds the library's template itself in a `DocumentTemplate` of its own, which reads the images from
its storage.

## Templates and parameters

Every template takes `title`, the document's title, when wanted; the label templates take `sheet`, one of
`letter30`, `letter10`, `letter6`, `a4x21` and `a4x8`, when the default does not suit.

| Name | Parameters | Default sheet |
|---|---|---|
| `paged_media.name_badges` | `event`; `badges`, each `name`, `subtitle`, `organisation`; `accent` (`#rrggbb`) | `letter6` |
| `paged_media.address_labels` | `labels`, each `lines` (a list of strings); `size` | `letter30` |
| `paged_media.product_labels` | `labels`, each `name`, `price`, `sku`, `detail` | `letter30` |
| `paged_media.shelf_labels` | `labels`, each `name`, `price`, `unit_price`, `sku`, `location` | `letter10` |
| `paged_media.gift_cards` | `card_title`; `cards`, each `amount`, `code`, `pin`, `recipient`, `message`, `valid_until`; `code_label`, `pin_label`, `valid_until_label` (English by default); `cut_lines` (true by default) | `letter10` |
| `paged_media.download_cards` | `instructions`, `address`; `cards`, each `title`, `code`, `subtitle`; `cut_lines` (true by default) | `letter10` |
| `paged_media.calendar` | `weekdays` (seven names from the first day); `months`, each `year`, `month`, `title` and `events`, lists of strings by day of the month; `first_day` (1 for Monday, the default, to 7 for Sunday); `accent` | A Letter page in landscape |
| `paged_media.large_print` | `blocks`, each a `heading` or a `paragraph`; `size` (18 by default), `heading_size` (24), `page_numbers` (true) | A Letter page |

A parameter missing or of the wrong kind is refused with `InvalidTemplateParameters`, which names the template, the
path of the value — `cards.2.code` — and what is expected; a value the library refuses, such as a gift card without a
code, throws its `InvalidDocument`. In a worker, both fail the message without a retry.

## Fonts

The templates write in Helvetica and Helvetica Bold unless `templates.font` and `templates.bold_font` name TrueType
files; with only `font`, it serves for both. The fonts are read once, when the container builds the service, so a
worker does not read them again for each job.

```yaml
phil_paged_media:
    templates:
        font: '%kernel.project_dir%/assets/fonts/NotoSans-Regular.ttf'
        bold_font: '%kernel.project_dir%/assets/fonts/NotoSans-Bold.ttf'
```

`templates.enabled: false` leaves the templates out, for an application that registers its own under other names.
