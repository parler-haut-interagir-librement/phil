# Forms

## The field

```php
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType;

$builder
    ->add('body', BlockEditorType::class, [
        'tools' => ['paragraph', 'header', 'list', 'quote', 'image', 'toggle'],
        'placeholder' => $translator->trans('article.body.placeholder'),
    ])
    ->add('summary', BlockEditorType::class, ['preset' => 'reduced', 'required' => false]);
```

The model is the canonical UJO JSON, a string, by default; with `document: true` it is a `Phil\Ujo\Document`. An
empty editor submits nothing: the field's value is `null`, so `NotBlank` applies.

| Option | Default | Effect |
|---|---|---|
| `preset` | the configured toolset | `full` or `reduced` |
| `tools` | the preset's, or the configured ones | The block types offered |
| `inline_tools` | the preset's, or the configured ones | The inline tools offered |
| `read_only` | `false` | The document is shown, not edited; a disabled field is read-only too |
| `placeholder` | the editor's own | The hint of an empty paragraph, already translated |
| `locale` | the request's | The language of the editor's labels |
| `messages` | `[]` | Labels replaced, by key ([javascript.md](javascript.md)) |
| `colors` | the configured ones | The colour tokens of the text colour tool |
| `upload_url`, `mentions_url`, `links_url` | the bundle's routes, when configured | The endpoints; `null` turns one off |
| `keep_foreign_blocks` | `false` | Blocks of types outside the toolset, already in the document, are kept rather than refused |
| `document` | `false` | The model is a `Document` rather than JSON |

An unknown tool raises `InvalidToolset` when the form is built: it is a mistake in the code, not in the input.

## What the server checks

The editor is a convenience; the request it sends is not trusted. On submission the JSON goes through two passes of
`phil/ujo`:

1. the application's `Ujo` — the service of `phil/ujo-bundle` — reads it strictly, within its size and depth limits,
   checks every block against its type and puts it in canonical form;
2. the toolset's normalizer narrows it: a block type the toolset leaves out fails the field, unless
   `keep_foreign_blocks` is set; an inline element it leaves out is unwrapped, its text kept.

A failure shows on the field as `This value is not a valid UJO document: …`, with the library's message naming the
path, as `UjoType` of `phil/ujo-bundle` does. To restrict the types further, or to check a document set by code,
add `phil/ujo-bundle`'s `ValidUjo` constraint to the property.

## Foreign blocks

A document may hold blocks the editor does not create — a stat block, a footnote, a raw block —, written by another
tool or converted from another format. The editor shows each one as a card naming its type: it can be moved,
duplicated and deleted, never edited, and is written back unchanged. With the default `keep_foreign_blocks: false`,
such a document fails the field when the type is outside the toolset; set the option where documents come from
elsewhere.

## The form theme

`@PhilBlockEditor/form/block_editor.html.twig` draws the widget — a hidden input holding the JSON, the editor's element
with its configuration as one `data-…-config-value` attribute, and a polite live region — and gives the label an
identifier the editor refers to with `aria-labelledby`. The row, the errors and the help come from the application's
own form theme. To draw the editor somewhere else, render the widget alone with `form_widget(form.body)`.

Without JavaScript, the hidden input keeps the document as it was received, so submitting the form changes nothing.
