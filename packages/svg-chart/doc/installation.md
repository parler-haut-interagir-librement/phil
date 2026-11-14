# Installation

```sh
composer require phil/svg-chart
```

The package needs PHP 8.5 or later, `ext-mbstring`, phil/svg-sanitizer, which cleans an image before it is drawn as
a PNG, and phil/fonts, which reads the fonts it is drawn with.

## The resvg extension

The PNG images need the `resvg` PHP extension, which is optional: without it, the charts are SVG images and
`ResvgRasterizer::isAvailable()` is false. It is installed with [PIE](https://github.com/php/pie), for PHP 8.3 to 8.5,
thread-safe builds such as FrankenPHP's included:

```sh
pie install resvg-php/resvg
```

PIE builds it from its source, which needs a Rust toolchain, 1.85 or later; follow the extension's own guide for the
source of the renderer it builds. Install the extension in the image the application is built in, and keep the fonts
the images are drawn with beside the application: the renderer reads no font of the system.
