<!-- phil-dev source-sha256=9876433156bf355dba78ed94c04dcba7e4150692fc560aa16d4bcad66b48e4cb -->
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

Le préfixe est vérifié à la construction du conteneur. Changer le préfixe ou la taille après l'attribution de
certificats déplace leurs indices vers d'autres listes : les attestations déjà émises continuent de pointer vers les
anciennes.

`lock_factory` désigne un service qui doit exister, sauf la valeur par défaut `lock.factory`, qu'on laisse de côté
quand il n'y en a aucun; [Formations et attribution](courses-and-awarding.md#deux-attributions-à-la-fois) dit ce que le verrou
protège.

`reminders.days` donne les seuils des [rappels](recertification.md#rappels-avant-la-fin); l'ordre et les doublons n'ont
pas d'importance.

`bake: true`, `archival: true` et `sign: true` exigent l'URL de Gotenberg dans `phil_paged_media.gotenberg.url`; sans elle, le rendu d'un
PDF lève `NotConfigured` de `phil/paged-media-bundle`. `sign: true` exige aussi le certificat de signature de
`phil_paged_media.signing`.
