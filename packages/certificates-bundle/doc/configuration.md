# Configuration

```yaml
phil_certificates:
    # Service id of the PSR-20 clock.
    clock: clock

    # Service id of the LockFactory the status index of an award is taken under, null for none. The default is used
    # when FrameworkBundle registers it; its store must be shared by every server that awards.
    lock_factory: lock.factory

    reminders:
        # The days before the end of a certificate at which CertificateExpiring is dispatched, once each; from 1 to
        # 3650, at least one.
        days: [60, 30, 7]

    status_lists:
        # The status lists the certificates fill: certificates-0, certificates-1 and so on. Up to 50 letters, digits,
        # dashes and underscores.
        prefix: certificates
        # The indexes of each list: phil_open_badges.status_lists.size, or less.
        size: 131072
        # Service id of the application's own StatusLists, asked for the lists of other names.
        others: null

    pdf:
        # letter or a4, turned landscape.
        page_size: letter
        # TrueType font files for the text and for the titles and the name; Helvetica and Helvetica Bold when null,
        # which print Latin-1 only.
        font: null
        bold_font: null
        # Whether the signed credential is embedded in the PDF, through Gotenberg.
        bake: true
        # Whether the PDF is repaired to PDF/A-3b, through Gotenberg, after the credential is embedded.
        archival: false
        # Whether the PDF is signed with the certificate of phil_paged_media.signing, last.
        sign: false

    register:
        # false leaves the controller out; the routes then answer nothing.
        enabled: true
        # The Twig layout the pages extend; it defines the "title" and "content" blocks.
        layout: '@PhilCertificates/layout.html.twig'
```

The prefix is checked when the container is built. Changing the prefix or the size after certificates were awarded
moves their indexes to other lists: the credentials already issued keep pointing at the old ones.

`lock_factory` names a service that must exist, except the default `lock.factory`, which is left out when there is
none; [Courses and awarding](courses-and-awarding.md#two-awards-at-once) says what the lock guards.

`reminders.days` gives the thresholds of [the reminders](recertification.md#reminders-before-the-end); the order and
the duplicates do not matter.

`bake: true`, `archival: true` and `sign: true` need Gotenberg's URL in `phil_paged_media.gotenberg.url`; without it,
rendering a PDF throws `NotConfigured` from `phil/paged-media-bundle`. `sign: true` needs the signing certificate of
`phil_paged_media.signing` too.
