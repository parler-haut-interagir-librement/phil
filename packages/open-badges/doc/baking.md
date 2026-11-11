# Baking

A baked image carries its own credential: the badge a recipient shares is the proof.

```php
use Phil\OpenBadges\Baking\PngBaker;
use Phil\OpenBadges\Baking\SvgBaker;

$png = PngBaker::bake($badgePng, $tokenOrSecuredDocument);
$credential = PngBaker::extract($png);      // the token or the JSON, or null

$svg = SvgBaker::bake($badgeSvg, $tokenOrSecuredDocument);
$credential = SvgBaker::extract($svg);
```

What is baked is a secured credential: a compact JSON Web Token, or a document with its Data Integrity proof (an
array, encoded as JSON, or its JSON). Anything else raises `InvalidImage`. Baking again replaces the credential: an
image holds a single one, and one holding two is refused when read. `extract()` checks only the form of what it finds;
pass it to [the verifier](verifying.md).

## PNG

The credential is the text of an `iTXt` chunk whose keyword is `openbadgecredential`, uncompressed, with no language
tag, written just before the `IEND` chunk. The other chunks are kept as they are, and bytes after `IEND` dropped.
Every chunk is checked — its length, its type, its CRC — and the image starts with `IHDR`; a compressed credential
chunk is refused. An image of more than 16 MiB is refused before it is read.

## SVG

The credential is an `openbadges:credential` element in the namespace `https://purl.imsglobal.org/ob/v3p0`, first
child of the `svg` root: a token in its `verify` attribute, a document as its character data — in two sections where
it holds `]]>`. The image is parsed, in UTF-8, by the DOM of PHP without the network; a NUL, a declaration of entities, an internal
subset of the document type, a root that is not an SVG `svg` element, or an image of more than 4 MiB is refused. The
image is written back with an XML declaration in UTF-8.

The rest of the image is kept as it is, scripts and event handlers included. An SVG served inline from the issuer's
origin runs what it holds, so sanitize an uploaded design before baking it, for example with `phil/svg-sanitizer`.
