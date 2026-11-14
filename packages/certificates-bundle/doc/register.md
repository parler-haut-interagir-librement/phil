# The register

The routes of `config/routes.php`, under the prefix the application gives:

| Route | Path | Answer |
|---|---|---|
| `phil_certificates_register` | `/` | The open courses and a form to find a certificate by its code; `?code=` redirects to the certificate's page, or answers `404` with the form |
| `phil_certificates_course` | `/courses/{slug}` | The course, its criteria, its end and the credits its recertification asks for, and the certificates listed, 50 a page, the latest first; `404` for an unknown slug |
| `phil_certificates_certificate` | `/{code}` | The certificate's page: valid, expired or revoked, with its recipient, course, dates, the certificates it renews and that renew it, and, once revoked, the reason |
| `phil_certificates_pdf` | `/{code}/certificate.pdf` | The PDF, inline |
| `phil_certificates_credential` | `/{code}/credential` | The signed credential, as a download, open to other origins |

The lookup forgives lower case, dashes and spaces, and the letters read as digits. The paths take the code as
twelve characters in upper case, as the QR code prints it.

## What the register discloses

A certificate's code is what gives access to it: sixty random bits, too many to guess. Its page shows the name printed
on it, the course and the dates — what the paper shows —, and the codes of the certificates it renews and that renew
it; never its holder's identifier. An unlisted certificate is on no page but its own, and its
page asks search engines not to index it (`X-Robots-Tag: noindex`); the PDF and the credential never are indexed.

The course pages may be cached five minutes by browsers and shared caches. A certificate's page and files are
revalidated on each request (`Cache-Control: no-cache`), so that a revocation shows at once; a revoked certificate's
PDF and credential answer `410 Gone`. Every page is sent with `Referrer-Policy: no-referrer`.

## The pages

The templates `@PhilCertificates/register.html.twig`, `course.html.twig` and `certificate.html.twig` extend the layout
of `register.layout`, which defines the blocks `title` and `content`; each is overridden like any bundle template. The
texts are in the translation domain `phil_certificates`, in English and French.
