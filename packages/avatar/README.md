# phil/avatar

**Generated avatars as SVG, from an opaque seed: initials, geometric shapes and pixel grids, accessible and safe.**

phil/avatar draws an avatar for every member who has not chosen a picture, from a seed: a stable opaque identifier of
the application, such as the member's UUID. The same seed always draws the same avatar, on every machine, with nothing
stored and no service called. The seed is never an email address nor a hash of one — a hash of an address is reversed
by a dictionary of addresses —, and the library refuses a seed with an `@`.

Three styles ship with it: the member's **initials** on a colored background, **geometric shapes** — circles, squares
and triangles — placed, sized and turned by the seed, and a mirrored **grid of pixels** in the manner of an identicon.
Initials are taken by grapheme clusters, so that accents, Hangul syllables and combining marks stay whole; names in
Han, Hiragana, Katakana and Hangul give their first character, and Arabic and Hebrew initials are laid out right to
left. Their ink always reaches the WCAG AA contrast of 4.5:1 with their background, which every palette checks.

An avatar is an SVG document, with a `<title>`, `role="img"` and `aria-label` when it has a name, hidden from assistive
technologies when it is decorative. Every element is written by the library from numbers, colors and escaped text:
no string of the caller reaches the markup as markup. An application adds styles of its own through a port, drawing on
the same canvas.

It is a plain PHP library, with no dependency beyond `mbstring`. Its generator keeps no state between calls and is safe
in a long-running worker.

## Status

| Part | State |
|---|---|
| Avatars as SVG documents and `data:` URIs, from an opaque seed, in three outlines and sizes from 16 to 1024 pixels | Implemented and tested |
| The initials style, by grapheme cluster, for Latin, Greek, Cyrillic, Indic, CJK, Arabic and Hebrew names | Implemented, tested and fuzzed |
| The shapes and pixels styles | Implemented and tested |
| Five palettes, palettes of the application, and the WCAG AA contrast of initials | Implemented and tested |
| Styles of the application, through the port `AvatarStyle` | Implemented and tested |
| Illustrated styles, imported as data under CC0 | Not started: planned for version 2 |
| Rasterizing to PNG or WebP | Not part of the library: the browser draws SVG, and an application that needs a bitmap converts it |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Avatars](doc/avatars.md) | Drawing an avatar, its options, its outline, and showing it on a page. |
| [Initials](doc/initials.md) | How initials are taken from names in every script. |
| [Palettes and contrast](doc/palettes.md) | The palettes, their inks, and the contrast of initials. |
| [Security and privacy](doc/security.md) | The seed, the markup safe by construction, the bounds on every input. |
| [Extension points](doc/extension-points.md) | Styles and palettes of the application, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests, and how to test code that draws avatars. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/avatar
```

```php
use Phil\Avatar\AvatarGenerator;
use Phil\Avatar\AvatarOptions;

$avatars = new AvatarGenerator();

echo $avatars->svg($member->uuid, new AvatarOptions(name: 'Ada Lovelace', size: 48));
// <svg … role="img" aria-label="Ada Lovelace"><title>Ada Lovelace</title>… AL …</svg>

$src = $avatars->dataUri($member->uuid, new AvatarOptions(style: 'shapes'));
```

## Requirements

PHP 8.5 or later with `mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
