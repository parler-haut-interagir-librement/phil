# Declarations

A `LicenseDeclaration` is the license of one subject, attached by a `SubjectReference`: a type — `book`, or a class
name — of 1 to 128 letters, digits, `_`, `.`, `:`, `\` or `-`, and an identifier of 1 to 64 printable ASCII characters.
An application object implementing `Licensable` gives its own reference.

```php
$declaration = LicenseDeclaration::declare(SubjectReference::of('book', 42), $catalog->get(LicenseCatalog::OGL), $now);
$declaration->describe(
    notice: 'Copyright 2026, Example Press.',
    reservedMaterial: null,
    productIdentity: 'The names, logos and artwork of the setting.',
    designatedContent: 'The rules of chapters 3 to 5.',
    at: $now,
);
$declaration->includeLicenseText($oglText, $now);   // the text the application provides
$declaration->attribute([
    Attribution::of(ResourceKind::Text, 'System Reference Document', LicenseCatalog::OGL, notice: $section15Line),
], $now);
$declaration->relicense($catalog->get(LicenseCatalog::ORC), $now);
```

Each change dates `changedAt()`. A blank text is `null`; texts are UTF-8 without control characters but tabs and line
breaks.

| Field | At most |
|---|---|
| Notice | 4 000 characters |
| Reserved Material, Product Identity, designated content | 20 000 characters each |
| License text | 100 000 characters |
| Attributions | 500 |

## Attributions

An `Attribution` credits one resource: its `ResourceKind` — `text`, `image`, `map`, `font`, `other` —, a title of at
most 255 characters, the code of its license, and optionally its author (255), an http or https address (2 048), the
notice its licensor asks to keep (4 000) and the changes made to it (1 000). The declaration stores them as JSON, in
order; `attributions()` reads them back.

## The repository

`LicenseDeclarationRepository` finds the declaration of a subject, adds and removes one; with Doctrine, adding and
removing take effect when the application flushes its entity manager. The license is kept by its code: a license
removed from the catalog leaves the declaration readable, and the compliance check reports it.
