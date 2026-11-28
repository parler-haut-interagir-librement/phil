# Forms and rendering

## Fields

`StoredMarkupTypeExtension` extends `phil/ujo-bundle`'s `UjoType` and, when it is installed, the block editor's
`BlockEditorType`. After the field has checked the submitted document, its transformer gives the document to
`BlockMarkup`:

- `math`, `chart` and `qrCode` blocks are drawn, and each drawing kept under `rendered` with its key;
- a drawing the client sent is dropped and made again: no markup a client writes reaches a page;
- an unchanged document is drawn from what it keeps, at no cost;
- `mermaid` blocks are left pending, unless `mermaid.async` is false;
- a block that cannot be drawn keeps no drawing; the form is still valid, and readers see the block's own rendering.
  The preview shows its author the reason.

The model keeps its form: canonical JSON, or a `Document` with `document: true`.

```php
$builder->add('body', UjoType::class, ['svg_blocks' => false]);   // this field's documents left as they are
```

## Formulas

`math` blocks are converted by `phil/latex-mathml`, within the limits of `math`: every construction it reads —
macros defined in the formula, colours, links, `\boxed`, `\tag`, rules in `array`, chemical notation in `\ce{…}` —
reaches the page, and a formula it refuses keeps no drawing, its reason shown by the preview. See its
`doc/commands.md` for the commands read and those refused.

## Rendering

The `HtmlRenderer` service of `phil/ujo-bundle` is decorated with `withStoredMarkup()`: `ujo_html` in Twig, `Ujo`, and
everything built on the renderer — `phil/epub`'s chapters, an HTML page sent to a paged-media engine — write the
trusted drawings:

```html
<div class="ujo-math"><math xmlns="http://www.w3.org/1998/Math/MathML" display="block">…</math></div>
<figure class="ujo-mermaid"><svg …>…</svg><figcaption>The flow</figcaption></figure>
<figure class="ujo-chart"><svg …>…</svg></figure>
<figure class="ujo-qr-code"><svg …>…</svg></figure>
```

A block without a trusted drawing falls back to its own rendering: the formula as text, the diagram's code in
`<pre class="mermaid">`, the chart's configuration in `data-chart`, the QR code's text.

## PDF

A PDF made from that HTML uses the drawings as they are kept. Engines that draw SVG — WeasyPrint, Chromium through
Gotenberg — draw diagrams, charts and QR codes. MathML is drawn by Chromium; WeasyPrint does not draw it, so a PDF made
by WeasyPrint shows formulas only through the application's own fallback.
