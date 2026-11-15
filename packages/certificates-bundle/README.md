# phil/certificates-bundle

**Certificates of completion a Symfony application awards, prints and lets anyone verify: courses and their pass
criteria, a PDF with a QR code and the signed credential inside, a public register, revocation, expiry reminders,
recertification and credits of continuing education.**

phil/certificates-bundle builds on [`phil/open-badges-bundle`](../open-badges-bundle/README.md) and
[`phil/paged-media-bundle`][paged-media-bundle]. The application defines its courses — what each
recognizes, the score, the minutes of training and the modules it takes to pass, how long a certificate stays valid —
and awards a certificate when a learner's attainment meets them. Each certificate carries an Open Badges 3.0
credential signed by the issuer, a code for the register and an index in the issuer's status lists.

Its PDF is drawn on a landscape page with a QR code that opens the certificate's page in the register, and the signed
credential embedded as an associated file, so that the file proves itself. The register shows any certificate by its
code — valid, expired or revoked — and, for each course, the certificates their holders chose to list. Revoking a
certificate sets its bit in the status list the issuer publishes, so verifiers elsewhere learn it too.

A certificate that ends is expired, never revoked. Before it ends, an event tells the application to remind its
holder; the holder is then recertified, with the credits of continuing education gathered meanwhile or a new
attainment, and receives a new certificate linked to the one it renews.

## Status

| Part | State |
|---|---|
| Courses, pass criteria (score, minutes, modules), validity in months, retiring, the end of a course | Implemented and tested |
| Awarding: criteria checked, Open Badges credential signed, recipient kept as a salted hash | Implemented and tested |
| The PDF: native drawing, QR code, credential embedded through Gotenberg as a PDF/A-3 associated file, optional PDF/A-3b and signature | Implemented and tested |
| The register: lookup by code, certificate pages, course pages with listed certificates, PDF and credential downloads | Implemented and tested |
| Revocation through Bitstring Status Lists, split into lists of a fixed size | Implemented and tested |
| Doctrine mapping and repositories | Implemented and tested |
| Awards under a lock of `symfony/lock`, started again when the storage finds the index taken; the events `CertificateAwarded` and `CertificateRevoked` | Implemented and tested |
| Expired distinct from revoked; reminders before the end through `CertificateExpiring`, once per threshold | Implemented and tested |
| Recertification: a new certificate linked to the one it renews, by credits of continuing education or an attainment | Implemented and tested |
| Credits of continuing education by member and period, toward a course, recorded once per source | Implemented and tested |
| Courses awarded on the completion another system knows, through the port `CourseCompletions`, the completion kept as evidence, once per completion | Implemented and tested |
| Console commands: define a course, award, recertify, revoke, status, export of a credential, reminders, credits | Implemented and tested |
| The QR code of the PDF decoded back to the certificate's address | Tested |
| The shape of the exported credentials against what Open Badges 3.0 describes, offline | Tested |
| 1EdTech conformance testing of the issued credentials | In progress: this version is not certified; the procedure and the export are ready, and [Validation](doc/validation.md) says how to verify a certificate today |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundles it builds on, the schema, the routes. |
| [Configuration](doc/configuration.md) | Every option, with its default. |
| [Courses and awarding](doc/courses-and-awarding.md) | Defining courses and their criteria, awarding a certificate. |
| [The PDF](doc/pdf.md) | What the certificate shows, the fonts, the embedded credential, the archival PDF, the signature. |
| [The register](doc/register.md) | The pages and downloads the routes serve, and what they disclose. |
| [Revocation](doc/revocation.md) | Revoking a certificate, the status lists. |
| [Completion sources](doc/completion-sources.md) | Courses awarded on what another system knows of their completion, through the port `CourseCompletions`. |
| [Expiry and recertification](doc/recertification.md) | The end of certificates and courses, reminders, recertification, credits of continuing education. |
| [Console commands](doc/console.md) | Defining courses, awarding, recertifying, revoking, showing, exporting, reminding and crediting from the console. |
| [Validation](doc/validation.md) | Where 1EdTech conformance testing stands, how to verify a certificate today, what is checked offline, and the run of 1EdTech's validator. |
| [Extension points](doc/extension-points.md) | The repositories, the source of completions, the URLs, the events, the print subject, the layout, the translations, other status lists. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application that awards certificates. |
| [Reference](doc/reference.md) | Every class of the bundle, with its role. |

## Quick start

```sh
composer require phil/certificates-bundle
```

```yaml
# config/routes/phil_certificates.yaml
phil_certificates:
    resource: '@PhilCertificatesBundle/config/routes.php'
    prefix: /certificates
```

```php
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;

$courses->define('first-aid', 'First aid', 'The gestures that save lives.', 'Score 70 % or more on the final assessment.', new PassCriteria(minimumScore: 70), validityMonths: 24);

$certificate = $awarding->award('first-aid', 'Ada Lovelace', 'ada@example.org', new Attainment(score: 84), listed: true);
// https://<site>/certificates/<code>, its PDF at /certificates/<code>/certificate.pdf
```

## Conformance

1EdTech conformance testing is in progress, and this version is not certified by 1EdTech. A certificate is verified
today in the register by its code, through the verification page of `phil/open-badges-bundle`, by any Verifiable
Credentials 2.0 verifier that reads VC-JWT or `eddsa-rdfc-2022`, or by running 1EdTech's validator against a
deployed issuer; see [Validation](doc/validation.md).

## Requirements

PHP 8.5 or later with `intl` and `sodium`, Symfony 8.2 or later, `phil/open-badges-bundle` and
`phil/paged-media-bundle` registered. Embedding the credential in the PDF needs Gotenberg; the register needs
TwigBundle; the bundled storage needs DoctrineBundle; the commands need the Console component; the lock of the
status index needs `symfony/lock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).

[paged-media-bundle]: https://github.com/parler-haut-interagir-librement/paged-media-bundle
