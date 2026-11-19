# Palettes and contrast

A palette holds the backgrounds an avatar is drawn on, chosen by the seed, and two inks: a light one and a dark one,
white and `#111111` by default. Initials, and the squares of the pixels style, take the ink of the higher contrast with
their background.

## The rule

Every background of a palette reaches a contrast of 4.5:1 at least with one of its inks: the WCAG 2.2 AA level for
normal text, stricter than the 3:1 of large text that initials at most sizes would need. `Palette` refuses a background
that reaches it with neither ink, so that initials are always readable, whatever the seed.

Contrast is computed as WCAG defines it: the relative luminance of each sRGB color, then `(L1 + 0.05) / (L2 + 0.05)`,
the lighter over the darker. `Color::contrastWith()` gives it.

## The palettes shipped

| Name | Backgrounds |
|---|---|
| `default` | Twelve: eight deep colors under white initials, four light ones under dark initials. |
| `pastel` | Ten light colors, under dark initials. |
| `vivid` | Ten saturated colors. |
| `earth` | Ten browns, greens and sands. |
| `grayscale` | Eight grays. |

`Palette::named($name)` gives one; `Palette::names()` lists them.

## A palette of the application

```php
$palette = Palette::fromHex(['#0b3d91', '#e0e7ff', '#7c2d12'], lightInk: '#ffffff', darkInk: '#0f172a');

$avatars->svg($seed, new AvatarOptions(name: $name, palette: $palette));
```

Colors are written `#rrggbb` or `#rgb`. A palette holds 1 to 64 backgrounds. The shapes style draws its shapes in the
palette's other backgrounds, or in its ink when the palette has one background.
