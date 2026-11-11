# Changelog

All notable changes to `phil/qr-code-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the package is launched
publicly, split to its public repository, this file keeps one "Added" section that describes the current state.

## NEXT - YYYY-MM-DD

### Added

- `PhilQrCodeBundle` and the `phil_qr_code` configuration: `error_correction`, `quiet_zone`, `dark`, `light`, `size`,
  `png_scale`, `eci`, `kanji`, `max_bytes`, `logos` (`path`, `zone`) and `route` (`enabled`, `cache_max_age`), each
  checked when the container is built.
- `QrCodes`: a text, a typed content or its array encoded with the defaults and drawn as SVG, PNG or a `data:` URI,
  each call overriding `level`, `quiet_zone`, `dark`, `light`, `size`, `scale`, `logo` and `title`; the payload
  bounded by `max_bytes`; `QrCodeDefaults` and `Logos`, the logos named by the configuration, read once.
- `ContentFactory`: the typed contents from arrays — `url`, `email`, `phone`, `sms`, `geo`, `wifi`, `mecard`, `vcard`,
  `event` —, their keys and the types of their values checked.
- `qr_code()` as a function and a filter, inline SVG or a `data:` URI of SVG or PNG; `qr_code_label()`; `qr_code_url()`.
- `<twig:PhilQrCode:QrCode>`: the code in a `<figure>`, what it holds in its `<figcaption>`, a link for a web address,
  the image named by `alt` or hidden behind the caption.
- `QrCodeController` and `QrCodeUrls`: `/qr-code.svg` and `/qr-code.png` in `config/routes.php`, at addresses signed
  with the application's secret, `public` and `immutable` for `cache_max_age`, an `ETag` and a `304`, `nosniff` and a
  policy that runs nothing; an address not signed is not found.
- `QrCodeBlockRenderer` and `SvgBlocksPass`: the `qrCode` blocks of UJO (Universal JSON Output) documents drawn with
  the defaults of the bundle for phil/svg-blocks-bundle; an optional integration, registered only when phil/svg-blocks
  is installed and only suggested.
- `PdfQrCodes`: codes drawn in PDF documents of phil/paged-media, one rectangle a run of dark modules, a PNG or JPEG
  logo fitted in its zone; an optional integration, registered only when phil/paged-media is installed and only
  suggested.
- Unit and functional tests, those of the optional integrations skipped with their reason when their package is
  absent; the documentation in `doc/`, one file per topic, indexed by the README;
  `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
