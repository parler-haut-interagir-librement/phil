# The PDF

`Phil\CertificatesBundle\Application\CertificatePdf` renders a certificate:

```php
$output = $certificatePdf->render($certificate, 'fr');   // a Phil\PagedMediaBundle\Application\Output
file_put_contents($output->filename, $output->contents);  // certificate-7KQ2M9XA04TZ.pdf
```

The register serves it at `/<code>/certificate.pdf` in the locale of the request.

## What it shows

One landscape page, Letter or A4: a title, the name printed on the certificate, the course's name on up to two lines,
the date it was awarded and, when it ends, the end of its validity, the issuer's name and the certificate's code. A QR
code in the bottom right corner opens the certificate's page in the register, whose address is written beside it, so
that anyone holding the paper can check that it still holds. The code is drawn at the error correction level M, each
run of dark modules one rectangle, inside its quiet zone of four modules.

The texts come from the translation domain `phil_certificates`, keys `pdf.*`, in the locale given, else the
translator's; the dates are formatted by `intl` in that locale and in PHP's default time zone, as the register's pages
show them. The PDF's title, author, subject and creation date name the certificate, the issuer, the recipient and the
moment it was awarded: the same certificate gives the same drawing.

## Fonts

Without fonts in the configuration, the text is set in Helvetica, which prints Latin-1 only. A site whose learners'
names use other scripts gives TrueType fonts that cover them:

```yaml
phil_certificates:
    pdf:
        font: '%kernel.project_dir%/assets/fonts/NotoSans-Regular.ttf'
        bold_font: '%kernel.project_dir%/assets/fonts/NotoSans-Bold.ttf'
```

The fonts are embedded, reduced to the glyphs used.

## The embedded credential

With `pdf.bake: true`, the default, the signed credential is embedded in the PDF by the `attach` step of Gotenberg's
PDF pipeline, as a PDF/A-3 associated file of relationship `Data`: `credential.jwt` (`application/vc+jwt`) for a
token, `credential.json` (`application/vc`) for a document with its proof. The file carries its own proof: a verifier
extracts the attachment and checks it, without the register. `phil_paged_media.gotenberg.url` names the Gotenberg the
pipeline runs on.

## The archival PDF

With `pdf.archival: true`, the PDF is then repaired to PDF/A-3b, the archival profile that admits an associated file
of any type, in the language of the certificate's locale. The result passes Gotenberg's PDF/A-3b validation with the
credential attached; the bundle's integration test checks both against a real Gotenberg 9. Repairing costs a second
step of the pipeline, so it is off by default.

## The signature

With `pdf.sign: true`, the PDF is signed with the certificate of `phil_paged_media.signing`, after the credential is
embedded and the file repaired, so that any change to the file shows.

## The print subject

`render()` names the print's subject — the type `certificate` and the certificate's identifier — for the listeners of
`phil/paged-media-bundle`; [Extension points](extension-points.md#the-print-subject) says how a site that checks the
licenses of its prints lets certificates through.
