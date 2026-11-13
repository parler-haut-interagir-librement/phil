# Integrations

## The native PDF writer

When `phil/paged-media` is installed, `PdfFonts` gives the TrueType faces of the catalogue to its native writer:

```php
$document->text(72, 720, 'Receipt', 18, $pdfFonts->get('Example Sans', 700));
```

`get(string $family, int $weight = 400, FontStyle $style = FontStyle::Normal)` looks for the face among the TrueType
files only, since the writer embeds TrueType outlines, and throws `FontNotFound` when none holds it. The writer then
applies the font's embedding permissions: it refuses a font whose licence forbids embedding, and embeds whole a font
that forbids subsetting.

## The media library

When `phil/media-bundle` is installed and its bundle enabled, `MediaFonts` reads a font uploaded to the media library:

```php
$file = $mediaFonts->read($media);

if (!$file->face()->embedding->usage->allowsEmbedding()) {
    // refuse it for the PDF, show the licence to the editor…
}
```

`read(Media $media)` reads the medium's current file with `FontFiles`, so WOFF2 follows the same rule, and names the
medium's file name in `FontUnreadable`. When the package is installed but the bundle not enabled,
`RequiredServicesPass` removes the service.
