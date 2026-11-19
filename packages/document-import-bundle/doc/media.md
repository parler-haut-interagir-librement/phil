# Media

## The media library

When `PhilMediaBundle` is registered, and `media.store` is `auto` or `library`, each image an import takes out —
every SVG image once sanitized — is uploaded to the media library by `MediaLibraryStore`:

- as a new medium, named by the file name of the image in the document;
- in the collection `media.collection`, which the store creates, named by its key, the first time it is used; in no
  collection when the option is not set;
- with the text alternative its author gave it as the medium's alternative text, cut to the 500 characters a medium
  keeps;
- at the address the `phil_document_import.media_address` closure gives the medium.

The address is, by default, the URL `media_url(medium)` of `phil/media-bundle` gives, which needs Twig as that
function does. When the media library hands out URLs that expire (`url_ttl`), or when the application shows its media
at addresses of its own, it replaces the closure:

```yaml
services:
    phil_document_import.media_address:
        class: Closure
        factory: [Closure, fromCallable]
        arguments: [['@App\Media\PermanentUrls', 'of']]
```

The address must be one a UJO image accepts: `http` or `https` with a host, or relative and not protocol-relative.

The media library serves an SVG file as an attachment rather than inline, so that even an image it did not receive
from the importer cannot run in the application's origin; an `<img>` shows it all the same.

## A folder

Without the media library, or with `media.store: folder`, the images go through `MediaFolder`: the address of an
image is `media.prefix` followed by its file name, and the application writes the bytes of `Imported::$media` there
itself.

## A store of the application's own

An application that keeps its files elsewhere aliases `Phil\DocumentImport\MediaStore` to a service of its own; the
importer then gives it every image, SVG images already sanitized:

```yaml
services:
    Phil\DocumentImport\MediaStore: '@App\Import\BucketStore'
```
