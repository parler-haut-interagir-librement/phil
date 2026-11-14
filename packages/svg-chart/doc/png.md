# PNG images

Some readers do not take SVG: e-mail clients, the image a page gives to be shared, a document that takes bitmaps only.
`ResvgRasterizer` draws an SVG image as a PNG through the `resvg` PHP extension, in the process, without a browser or a
service.

```php
use Phil\SvgChart\Raster\ResvgRasterizer;

if (ResvgRasterizer::isAvailable()) {
    $rasterizer = new ResvgRasterizer(['/srv/app/fonts/Inter-Regular.ttf', '/srv/app/fonts/Inter-Bold.ttf']);
    $png = $rasterizer->png(new SvgChartWriter()->write($chart), 2);
}
```

```php
new ResvgRasterizer(array $fontFiles, int $maxBytes = 1_048_576, int $maxPixels = 16_777_216)->png(string $svg, int $scale = 1): string
```

| Argument | Range |
|---|---|
| `$fontFiles` | one or more TrueType or OpenType files, collections included; WOFF and WOFF2 are refused |
| `$maxBytes` | the largest SVG drawn, 1 to 16 777 216 bytes |
| `$maxPixels` | the largest PNG drawn, its width times its height at the scale asked, 1 to 67 108 864 |
| `$scale` | 1 to 4: the PNG is the image's own size times the scale, 2 for screens of high density |

The rasterizer reads its fonts once, when it is built: keep one per worker, as a service. It holds nothing of a
request and may serve every request of a long-running worker.

## The extension is optional

The package works without the extension: the charts are SVG images, and only the PNG is not offered.
`ResvgRasterizer::isAvailable()` says whether the extension is loaded; without it, the constructor throws
`RasterizerUnavailable`, whose message says so. The extension is installed with PIE, which builds it from its Rust
source for PHP 8.3 to 8.5; see [Installation](installation.md).

`Contract\Rasterizer` is the port: code that needs a PNG takes a `Rasterizer`, and an application that draws PNG images
otherwise gives its own.

## Safety

An SVG drawn as a PNG is parsed and drawn in the PHP process, so every image is handled as if anyone had written it:

- it is refused whole when it is larger than `$maxBytes`, before it is read;
- it goes through phil/svg-sanitizer: scripts, event handlers, foreign objects and every reference that is not a
  fragment of the image itself are removed, and so are the raster images of `data:` addresses, which could hide an
  image of huge dimensions;
- the renderer reads no font of the system, only the files given: the family of the first one draws every text whose
  own families are not among them, generic families such as `sans-serif` and `system-ui` included, so that the same
  image draws the same pixels on every machine;
- the images an SVG names are confined to an empty directory of the package, so that no file of the machine is read,
  whatever path the SVG gives; the renderer has no network access;
- the size of the PNG is measured before it is drawn, and refused beyond `$maxPixels`;
- the renderer refuses a document without a size, of more than 1 000 000 elements, or larger than its own limits,
  `resvg.max_input_size` and `resvg.max_render_pixels` in `php.ini`.

Every refusal is a `RasterizationFailed`, with the reason in its message: nothing is ever drawn in part. A font that
cannot be read, of another format, or a limit out of range is an `InvalidRasterizer`, when the rasterizer is built.

The font files are trusted input: the renderer parses them. Never take their paths from a request.

## What is drawn

The text is shaped by the renderer: accents, Greek, Cyrillic, and the scripts that join or reorder their letters,
such as Arabic or Devanagari, draw as a browser draws them, provided a font given holds their glyphs. A chart of
`SvgChartWriter` is drawn at the size of its `viewBox`, its white background included. The `<title>` and `<desc>` of
the SVG do not survive in a PNG: give the image an `alt` text where it is shown, and the values as text beside it.
