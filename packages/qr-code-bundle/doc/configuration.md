# Configuration

```yaml
# config/packages/phil_qr_code.yaml
phil_qr_code:
    error_correction: M       # L, M, Q or H
    quiet_zone: 4             # 0 to 16 modules
    dark: '#000000'           # #rrggbb
    light: '#ffffff'          # #rrggbb
    size: 0                   # the width and height of an SVG in pixels, 0 to 4096; 0 lets its container size it
    png_scale: 8              # the pixels on a side of a module of a PNG, 1 to 32
    eci: true                 # announce bytes beyond ASCII as UTF-8
    kanji: true               # write the double-byte characters of Shift JIS in the kanji mode
    max_bytes: 2048           # the longest payload, 1 to 7089 bytes
    logos:
        brand: { path: '%kernel.project_dir%/assets/logo.svg', zone: 0.2 }
    route:
        enabled: false        # serve codes at signed addresses; import config/routes.php too
        cache_max_age: 31536000
```

Every key is checked when the container is built: a level, a colour, a size or a zone out of its range stops the
build with the reason.

## The defaults

The defaults apply to every code the bundle draws — in Twig, in the component, at the route, in UJO blocks and in PDF
documents —, and each call may override `level`, `quiet_zone`, `dark`, `light`, `size`, `scale`, `logo` and `title`
(see [Twig](twig.md)). Keep the dark colour dark and the light one light: readers need the contrast.

`max_bytes` bounds the payload of a code, so a template or a block never asks for a code of version 40 by mistake; a
payload beyond it is refused with `InvalidContent`.

## The logos

A logo has a name, a file — PNG, JPEG, WebP or SVG, at most 1 MiB, recognised by its bytes — and `zone`, the share of
the side of the code it takes, from 0.05 to 0.3 (0.2 by default). A code drawn with a logo is raised to level H and
made large enough for every block to correct the zone: see phil/qr-code's [logos](../../qr-code/doc/logos.md).

Templates name a logo; they never give a path, so no template reads a file the configuration did not list. A file is
read when its logo is first drawn, then kept for the life of the process. A PNG and a PDF place PNG, JPEG and WebP
logos — WebP in PNG only —; an SVG logo is drawn in SVG alone.
