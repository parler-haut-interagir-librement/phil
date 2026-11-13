# Forms

`Document::field()` places a form field on the last page: a text box, a check box, a radio group or a choice. A reader
fills it in a PDF viewer and saves or prints it, which makes a character sheet, an order form or a registration sheet
fillable. `render()` keeps the fields interactive; `render(flattenForm: true)` draws them into their pages, as they
are, for a copy that can no longer be changed.

```php
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Form\CheckBox;
use Phil\PagedMedia\Pdf\Form\ChoiceField;
use Phil\PagedMedia\Pdf\Form\RadioButton;
use Phil\PagedMedia\Pdf\Form\RadioGroup;
use Phil\PagedMedia\Pdf\Form\TextField;

$pdf = new Document();
$pdf->addPage();
$pdf->text(72, 720, 'Character sheet', 18);
$pdf->field(new TextField('name', 72, 680, 200, 20, border: Color::gray(0), tooltip: 'Character name'));
$pdf->field(new TextField('notes', 72, 560, 300, 100, multiline: true, border: Color::gray(0)));
$pdf->field(new CheckBox('inspiration', 72, 530, border: Color::black()));
$pdf->field(new RadioGroup('size', [
    new RadioButton('small', 72, 500),
    new RadioButton('medium', 92, 500),
    new RadioButton('large', 112, 500),
], selected: 'medium', border: Color::black()));
$pdf->field(new ChoiceField('class', 72, 460, 150, 20, ['war' => 'Warrior', 'mage' => 'Mage'], 'mage'));

file_put_contents('sheet.pdf', $pdf->render());               // fillable
file_put_contents('sheet-filled.pdf', $pdf->render(true));    // flattened
```

## Fields

Every field has a name, unique in the document, without a period (a period would make it the child of another field)
and of 127 bytes at most. It is placed from the bottom left corner of its box, in points from the bottom left corner
of the page, like everything the writer draws.

| Field | What it is | Its value |
|---|---|---|
| `TextField` | A text box, on one line or `multiline`, with an optional `maxLength` | The text it holds |
| `CheckBox` | A square, checked or not | Its `exportValue` (`Yes` by default) when checked, `Off` otherwise |
| `RadioGroup` | `RadioButton`s of which one is chosen, each with its own value and place | The chosen button's value, or `Off` |
| `ChoiceField` | A drop-down list (`combo`, the default), which may be `editable`, or a list box | The chosen option's value, or the text typed in an editable combo box |

A `ChoiceField` takes a list of labels, each its own value, or labels by value: `['war' => 'Warrior']` shows
*Warrior* and gives `war`. Its `options` are pairs of a value and a label, and `shown()` is what the field shows.

Each field may be `required` or `readOnly`, and take a `tooltip`, which assistive technology reads. Its `color` is the
colour of its text or mark, black by default; its `border` and `background` outline and fill its box, and nothing is
drawn when they are not given, so a field can sit in a box the page already prints.

A value is checked when the field is built: UTF-8 text, on one line unless the field is multiline, no longer than its
`maxLength`; a selected value among the field's own, except in an editable combo box; two buttons or two options of one
value, and an export value of `Off`, are refused. Each refusal is an `InvalidDocument`, as is a second field of the
same name, or a field placed before any page.

## Appearances

The writer draws every field's appearance when the field is placed: the text in its font, wrapped to the box when it
is multiline, the chosen option of a combo box, the options of a list box with the chosen one highlighted, the check
mark and the dot of the buttons, drawn as paths, without a symbol font. A viewer shows the field as it is without
generating anything, and the document asks for no generation (`NeedAppearances` is never set). A viewer draws a field
again when its value changes, with the font and colour the field names (its `/DA`) and the border and background it
declares (its `/MK`). An appearance of fewer than 256 bytes is written uncompressed, which costs less than
compressing it.

## Fonts

A text box or a choice types in `Helvetica` by default, or in the `StandardFont` or `TrueTypeFont` it is given. While
the form is interactive, a TrueType font a field types in is embedded whole, every glyph its character map holds,
since the reader may type any of them; once the form is flattened, it is reduced to the glyphs printed, like any other
text. A font whose licence forbids embedding is refused when it is read, as always.

## PDF/A

A document written to `Conformance::PdfA2u` keeps its fields: each widget is printable, every appearance is given,
and nothing generates one. Its text boxes and choices must type in a `TrueTypeFont`, since PDF/A embeds every font: a
standard font is refused when the field is placed, as it is for `text()`. Check boxes and radio buttons need no font.

## Flattening

`render(flattenForm: true)` draws each field's appearance, in the state it holds, over its page's content, as a form
XObject, and writes no form: the values become part of the page. The same `Document` renders both ways, so an
application keeps one sheet and writes the copy it needs.

## Reading a form back

The library writes forms; it does not read a filled one. Gotenberg reads the fields of any PDF (`Gotenberg::form()`)
and fills or flattens them (`SetForm`, `FlattenForm`): see [Reading a PDF](reading.md) and [Stages](stages.md).
