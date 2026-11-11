# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `array_any()`, `new` without parentheses |
| `ext-gd` with FreeType, PNG and WebP support | Every image is decoded, drawn and encoded by GD; texts are drawn by FreeType through GD |
| `ext-mbstring` | The characters of a text, checked against the character map of its font |
| `phil/fonts` | The format, names and character map of a font file |

`php -r 'print_r(gd_info());'` shows what a GD build supports: `FreeType Support`, `PNG Support` and `WebP Support`
must be true. The official PHP Docker images build GD with them when `docker-php-ext-configure gd --with-freetype
--with-webp` runs before `docker-php-ext-install gd`. A call that needs a missing part throws `MissingCapability`.

## Installing

```sh
composer require phil/print-compositor
```

It is a library, not a bundle: there is nothing to register. `PrintCompositor`, `TextFitter` and `MockupCompositor`
hold no state between two calls, so one instance of each can serve every job.

## Why GD rather than Imagick

Both extensions can do the work; GD is the one the library is written on, for these reasons:

- **It ships with PHP.** GD is a bundled extension, enabled by every distribution and by the official Docker images,
  and follows each PHP release on the day it is published. Imagick is a PECL extension over a separate ImageMagick
  install, and has lagged behind new PHP versions; the library is tested on the next PHP version from its first
  release candidate.
- **It has what a print file and a mockup need, and nothing more.** True colour with an alpha channel, resampling,
  FreeType text, PNG with its resolution written in the file, and WebP. A transfer or DTG printer takes sRGB PNG
  files, so ImageMagick's colour management and CMYK output bring nothing to these files.
- **Its surface is small.** ImageMagick decodes dozens of formats through delegates and needs a security policy to
  keep untrusted files away from them; GD decodes only the common web formats. The library also checks the size an
  image declares before decoding it.
- **What GD lacks is written in the library.** GD has no blend mode that keeps fabric shading on a dark garment, so
  the mockup shading is computed pixel by pixel ([Mockups](mockups.md)); FreeType's bounding box can be a few pixels
  off the ink, so a text is measured on the pixels it draws ([Texts](texts.md)).

The cost of that choice is speed on very large images: the shading of a mockup runs in PHP over the pixels of each
zone, which is fast enough for photos of a few megapixels, the size a site shows.
