# Media

## The images

Each image an import takes out is a `Media`:

| Property or method | Content |
|---|---|
| `path` | Its path in the archive (`word/media/image1.png`, `Pictures/1000.png`), or `image-N.ext` for an HTML paste |
| `contents` | Its bytes |
| `description` | The text alternative its author gave it |
| `mediaType` | Read from the bytes: PNG, JPEG, GIF, WebP, AVIF, BMP, SVG, TIFF, EMF, WMF, or `application/octet-stream` |
| `isWebImage()` | Whether a browser shows it: PNG, JPEG, GIF, WebP, AVIF, SVG or BMP |
| `filename()` | The last segment of the path, with any character other than a letter, a digit, `.`, `_` or `-` replaced by `-`, ending with an extension of the type its bytes show: `chart.html` holding a PNG image is named `chart.html.png` |

Only images a browser shows become image blocks; the others are reported. The same image used twice is one entry of
`Imported::$media` and two blocks.

## SVG images

An SVG image can carry scripts, event handlers and references to other files. Before it is given to the media store,
every SVG image goes through the `SvgSanitizer` of `phil/svg-sanitizer`: the `Media` the store receives, and that
`Imported::$media` holds, has only what the sanitizer keeps. Each part removed is reported as
`Omitted::SvgContent`, with the reason of its removal as detail; an image the sanitizer refuses whole — malformed,
too large, declaring entities — is not stored, and is reported as an image a browser does not show. Every SVG image
goes through it, wherever the document holds it: in the body, a table cell, a footnote or an endnote, a list item, in
an archive of DOCX or ODT, embedded in a flat ODT or an RTF, or in a `data:` address of HTML or Markdown.

The importer takes the sanitizer, and so its policy:

```php
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;

$importer = new DocumentImporter(svg: new SvgSanitizer(new Policy(maxBytes: 1_048_576)->withDataImages(false)));
```

## The media store

A `MediaStore` answers the address an image block shows:

```php
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaStore;

final readonly class UploadStore implements MediaStore
{
    public function __construct(private Storage $storage) {}

    public function store(Media $media): string
    {
        return $this->storage->put('imports/'.hash('sha256', $media->contents).'.'.pathinfo($media->filename(), PATHINFO_EXTENSION), $media->contents);
    }
}
```

The address must be one a UJO image accepts: `http` or `https` with a host, or relative and not protocol-relative.
Another address fails the check of the document with `Phil\Ujo\Exception\InvalidDocument`.

`MediaFolder`, the default store, keeps nothing: it answers its prefix — `media/` unless given — followed by the
file name, and the application writes `Imported::$media` there itself:

```php
foreach ($imported->media as $address => $media) {
    file_put_contents($publicDirectory.'/'.$address, $media->contents);
}
```

Two different images of one document with the same file name — in different folders of an archive, or embedded
under one name — would get the same address from `MediaFolder`. The importer tells them apart: when the address the
store answers already holds other bytes, it asks again for the image under its path numbered — `media/image1-2.png` —,
up to a thousand times, and reports an image it still cannot place as `same name`. The same image met twice keeps one
address.

## Images where only text goes

A table cell, a list item and a note hold only text. An image met there is still taken out and given to the store,
and its image block comes after the table, after the list, or after the paragraph that refers to the note.
