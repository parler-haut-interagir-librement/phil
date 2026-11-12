# The library

`MediaLibrary` is the service an application calls; `MediaRepository` saves what it changes.

## Uploading

```php
$media = $library->upload($path, 'Été 2026.png', $collection);   // collection optional
```

The media type is read from the file's contents, not from its name; an image's dimensions are read too. The file is
written under `originals/{id}/{version}/{safe name}` and the medium saved. A file that cannot be read is refused with
`InvalidMedia`.

An SVG image is purified first, when `phil/svg-sanitizer` is installed and `svg.purify` is on, as it is by default:
`SanitizingSvgPurifier` removes what could run, load or embed anything — scripts, event handlers, `foreignObject`,
external references, CSS its policy refuses —, then `SvgMinifier` drops comments, processing instructions, `metadata`
and the whitespace between elements, and rounds the coordinates of `d`, `points` and `transform` to `svg.precision`
decimals, 3 by default. What is kept is the purified image, and its size the purified size; an image the sanitizer
refuses whole — malformed, not SVG, declaring entities, too large — is refused with `InvalidMedia`. The text of
`text`, `tspan`, `textPath`, `title`, `desc` and `style`, and anything under `xml:space="preserve"`, keeps its
whitespace.

With a placeholder format, each version of a raster image also gets its tiny preview: see
[Responsive images](images.md#placeholders).

## Replacing

```php
$library->replace($media, $path, 'Été 2026, recadrée.png');
```

The medium keeps its identifier, so every page that shows it shows the new file. Its version goes up by one and the
previous file is kept, recorded as a `MediaVersion`; `MediaRepository::versions($media)` lists them, newest first.
URLs carry the version, so the old file's URLs stop answering and browsers fetch the new one; the formats rendered
from the old file are deleted.

## Describing and licensing

```php
$media->describe('La fête', alt: 'Des gens qui dansent sous des lanternes', credit: 'Ada');
$media->license('CC-BY-4.0', AiDisclosure::Assisted);
$repository->save($media);
```

| Field | Rule |
|---|---|
| Title, credit | Up to 255 characters; blank is none |
| Alternative text | Up to 500 characters; blank is none |
| License | An SPDX identifier — `CC-BY-4.0`, `CC0-1.0` — or `all-rights-reserved`, the default |
| AI disclosure | `None`, made by people; `Assisted`, made with a generative tool's help; `Generated` |

The AI disclosure is what the people who see the medium are told; showing it is the application's choice.

With `phil/creation-disclosure` installed, a medium declares how it was made as an IPTC digital source type, more
precisely than the three values of `AiDisclosure`:

```php
use Phil\CreationDisclosure\DigitalSourceType;

$media->disclose(DigitalSourceType::CompositeWithTrainedAlgorithmicMedia);
$media->digitalSourceType();   // 'compositeWithTrainedAlgorithmicMedia'
$media->aiDisclosure();        // AiDisclosure::Assisted
```

The AI disclosure follows the type: `Generated` for media created with generative AI, `Assisted` for media edited with
it or that may hold generated elements, `None` otherwise. A retired type is declared as its replacement. The code is
kept in the `digital_source_type` column and given to schema.org as `digitalSourceType`; `license()` with an AI
disclosure forgets it.

## Collections

```php
$events = MediaCollection::create('events', 'Événements');
$repository->save($events);
$media->moveTo($events);
$repository->inCollection($events, limit: 50, offset: 0);   // newest first; null, the media in none
```

Deleting a collection leaves its media in none.

## Reading several media

```php
$found = $repository->getMany(...$ids);   // array<string, Media>, by identifier, in the order asked
```

`MediaRepository::getMany()` reads several media in one query: a
gallery, the image emojis of a page. An identifier asked twice is read once, one that names nothing is left out. The
query reads at most `DoctrineMediaRepository::BATCH` identifiers, 500, and one more query reads each 500 beyond, so the
number of bound parameters stays under the limits of every database.

## Deleting

```php
$library->delete($media);
```

The medium, its versions, its original files and its rendered formats are gone. Moving it to `phil/trash-bundle`'s
trash instead is the application's choice. A record that still shows the medium, in a column of type `phil_media`,
decides first: [Records that show media](doctrine.md) describes `restrict`, `set_null` and `keep`, and
`MediaLibrary::usages($media)` lists where a medium is shown, column by column.

## Renaming and moving

```php
$media->rename('Été 2026, recadrée.png');   // the name shown and saved; the file and its key stay
$media->moveTo($collection);               // or null, outside every collection
$repository->save($media);
```

`MediaRepository::collections()` lists every collection by name. The [file explorer](admin.md) does the same from a
page.
