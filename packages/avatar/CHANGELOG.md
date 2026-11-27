# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `phil/avatar` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `AvatarGenerator`, which draws an avatar as an SVG document or a `data:` URI from a seed, the same bytes for the same
  seed and options, labelled for assistive technologies or hidden from them.
- `AvatarOptions`: style, size from 16 to 1024 pixels, outline, palette, name, label and number of letters.
- `Seed`, an opaque identifier that refuses an email address, and `Random`, the choices drawn from it by SHA-256.
- `Initials`, by grapheme cluster, uppercased one for one, the first character alone for CJK names, right to left for
  Arabic and Hebrew.
- `Color` and `Palette`, with the WCAG relative luminance and contrast; every background readable at 4.5:1 with one of
  the inks; the palettes `default`, `pastel`, `vivid`, `earth` and `grayscale`.
- `Canvas`, which writes every element of an avatar itself from numbers, colors and escaped text, and `Shape`.
- The port `AvatarStyle` and the styles `InitialsStyle`, `ShapesStyle` and `PixelsStyle`.
- `AvatarException`, implemented by `InvalidAvatar`.
- A fuzz target for the initials and the avatars drawn from them, and a benchmark of each style.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
