# Testing

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, checks the configuration and the services it registers, `QrCodes` against
phil/qr-code's writers, every typed content from its array and each refusal, the Twig extension, the component, the
signed addresses and the controller, the renderer of UJO blocks, the compiler pass, and the rectangles `PdfQrCodes`
writes in a PDF stream.

The functional suite, under `tests/Functional`, boots a small application with FrameworkBundle, Twig, Twig
components, and phil/ujo-bundle with phil/svg-blocks-bundle when they are installed: it renders a page that draws
codes every way, follows the signed addresses to the route — cache headers, `304`, refusals —, and draws a `qrCode`
block through phil/svg-blocks-bundle.

The tests of the optional integrations are skipped, each with the reason, when their package is not installed: the
renderer of UJO blocks and its registration without phil/svg-blocks, the `qrCode` block of the functional suite
without phil/svg-blocks-bundle, which the test kernel then leaves out with phil/ujo-bundle, and `PdfQrCodes` and its
registration without phil/paged-media. Installed on its own, the bundle passes with them skipped; in the monorepo,
where every package is there, they all run.

In the monorepo, Infection holds the package at 100 % of covered mutants.

## Testing code that draws codes

A code is deterministic: compare the SVG, the PNG or the payload with a value of your own, or build a `QrCodes` with
the defaults the test needs.
