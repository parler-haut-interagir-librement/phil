# The attribution form

With `symfony/form` and `symfony/validator`, `AttributionType` edits the credit of one resource and gives back an
`Attribution`, or `null` for an empty form.

| Field | Type | What it holds |
|---|---|---|
| `kind` | `EnumType` of `ResourceKind` | A text source, an image, a map, a font or another resource. |
| `title` | `TextType`, required | The title of the resource. |
| `license` | `ChoiceType` | The licenses of the catalog, named in the reader's language. |
| `author` | `TextType` | Who created it. |
| `url` | `UrlType` | An absolute http or https address. |
| `notice` | `TextareaType` | The notice its licensor asks to keep. |
| `changes` | `TextareaType` | How the work changed the resource. |
| `unchanged` | `CheckboxType` | "No modifications", which fills the changes. |

Each text is checked as `Attribution` checks it — its length once trimmed, UTF-8, no control character but line
breaks and tabs —, so the errors show on their fields.

## The changes

When the resource's license asks for the changes to be indicated — Creative Commons Attribution and each of its
variants —, the changes are required: an empty field shows `form.attribution.changes_required`, which names the
license. An unchanged resource says so: the box "No modifications" fills the changes with
`form.attribution.unchanged_text` in the reader's language when the form is submitted. The form works without
JavaScript: shown again, the field holds the sentence, and the box is checked for changes that are that sentence.

```php
$form = $this->createForm(AttributionType::class, $attribution);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $declaration->attribute([...$declaration->attributions(), $form->getData()], $this->clock->now());
}
```

A list of resources is a `CollectionType` of `AttributionType`, given to `LicenseDeclaration::attribute()` once
submitted; the entity manager flushes the declaration.

## Rendering

The block prefix is `phil_licensing_attribution`; the labels, the help texts and the messages are in the domain
`phil_licensing`, in English and French. The fields come in reading order, "No modifications" right after the changes,
and render with the form theme of the application.
