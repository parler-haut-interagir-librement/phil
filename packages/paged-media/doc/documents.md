# Documents

## Pages

A `Document` is a list of pages. Each page has a size, a `PageSize` in points — a seventy-second of an inch:

```php
$pdf = new Document(PageSize::letter());   // every page is Letter unless addPage() says otherwise
$pdf->addPage();
$pdf->addPage(PageSize::a4()->landscape());
```

`PageSize::of($width, $height)` takes points, `PageSize::ofMillimetres()` millimetres; a side is between 1 and 14 400
points, the largest that PDF readers are required to display. `letter()`, `a4()` and `a5()` are ready made.

Coordinates start at the bottom left corner of the page, and drawing goes on the last page added: drawing before any
page throws `InvalidDocument`.

## Text

```php
$pdf->text(54, 700, 'Receipt R-001', 14, StandardFont::HelveticaBold);
$pdf->textRight(558, 680, '12.50', 10, StandardFont::Helvetica, Color::gray(80));
```

`text()` puts the start of the baseline at (x, y); `textRight()` ends the text at x, for amounts and page numbers. The
size is in points and above zero. Line breaks in a text are printed as spaces: one call is one line, and
`Document::wrap()` cuts a long text into lines ([Fonts](fonts.md)).

## Lines and rectangles

```php
$pdf->line(54, 660, 558, 660, 0.5);
$pdf->rectangle(54, 600, 200, 40, fill: Color::hex('#f2f2f2'));
$pdf->rectangle(54, 600, 200, 40, Color::hex('#f2f2f2'), Color::black(), 0.25);
```

A rectangle is filled, outlined, or both; without either colour it is outlined in black. Colours are `Color::gray()`,
`Color::rgb()` or `Color::hex()`, components from 0 to 255.

## Images

`image($image, $x, $y, $width, $height)` places an `Image` with its bottom left corner at (x, y), stretched to the
width and height given ([Images](images.md)). An image placed several times is written once.

## Form fields

`field($field)` places a text box, a check box, a radio group or a choice on the last page, with its appearance
drawn. `render()` keeps the fields fillable; `render(flattenForm: true)` draws them into their pages. See
[Forms](forms.md).

## Metadata

```php
new Document(PageSize::a4(), new Metadata(title: 'Facture F-001', author: 'Accounting'));
```

The title, author, subject, keywords and creator go into the information dictionary, with `phil/paged-media` as the
producer; text outside printable ASCII is written in UTF-16.

## Determinism

The same calls give the same bytes: no date unless `Metadata` is given one, no random identifier, fonts and images in
the order they are first used. Tests can compare a document with a reference file.

## Compression

Content streams and embedded fonts are compressed with zlib, as are the appearances of form fields from 256 bytes.
`new Document(compress: false)` writes them as they are, which makes a document readable in a text editor while
debugging.
