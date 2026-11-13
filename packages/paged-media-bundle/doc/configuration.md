# Configuration

```yaml
phil_paged_media:
    gotenberg:
        url: ~                 # Gotenberg's base URL, such as http://gotenberg:3000; none when null
        username: ~            # basic authentication, with --api-enable-basic-auth
        password: ~
        timeout: ~             # seconds; the HTTP client's default when null
        http_client: http_client
    signing:
        certificate:
            type: none         # none, file, inline or vault
            path: ~            # file: the PKCS#12 file, or the PEM certificate with key_path
            key_path: ~        # file: the PEM private key
            password: ~        # file, inline: the password of a PKCS#12 file
            certificate: ~     # inline: the PEM certificate, or the bytes of a PKCS#12 file
            key: ~             # inline: the PEM private key
            vault_address: ~   # vault: HashiCorp Vault or OpenBao
            vault_token: ~
            vault_path: ~      # vault: the secret holding "certificate" and "key" in PEM
            vault_mount: secret
        timestamp_url: ~       # the RFC 3161 timestamp authority
        reason: ~              # what every signature says, unless a call says otherwise
        location: ~
        contact_info: ~
        name: ~
        pades: false
    storage:
        directory: '%kernel.project_dir%/var/paged-media'
    jobs:
        directory: '%kernel.project_dir%/var/paged-media-jobs'   # readable by its owner only
        remove_failed: true                                      # forget the job of a message failed for good
        cache_pool: ~                                            # a PSR-6 pool service instead of the directory
        lifetime: ~                                              # seconds a job is kept in the pool
    templates:
        enabled: true                                            # the library's printed templates
        font: ~                                                  # a TrueType file; Helvetica when null
        bold_font: ~                                             # a TrueType file; the regular font, or Helvetica Bold, when null
```

A certificate of type `file` needs its `path`, an `inline` one its `certificate`, a `vault` one its address, token and
path; the container refuses one without. Keep secrets out of the file: `%env(SIGNING_PASSWORD)%`,
`%env(SIGNING_CERTIFICATE)%` from Symfony's secrets vault, `%env(VAULT_TOKEN)%`.

## Services

| Service | When |
|---|---|
| `Phil\PagedMediaBundle\Application\PagedMedia` | Always: the one service, public |
| `Phil\PagedMedia\Gotenberg\Gotenberg` | When `gotenberg.url` is set, on the `http_client` service |
| `Phil\PagedMedia\Gotenberg\Signing\CertificateSource` | When `signing.certificate.type` is not `none`: `FileCertificateSource`, `InlineCertificateSource` or `VaultCertificateSource` |
| `Phil\PagedMedia\Gotenberg\Signing\TimestampAuthority` | When `signing.timestamp_url` is set |
| `Phil\PagedMediaBundle\Application\DocumentTemplates` | Always: the services tagged `phil_paged_media.template`, by name |
| `Phil\PagedMediaBundle\Application\Port\PdfStorage` | Always: an alias of `DirectoryStorage` on `storage.directory`; alias it to a service of your own to keep the files elsewhere |
| `Phil\PagedMediaBundle\Application\Port\JobStore` | Always: an alias of `DirectoryJobStore` on `jobs.directory`, or of `CacheJobStore` on the pool `jobs.cache_pool` names; alias it to a service of your own to keep the jobs elsewhere |
| `Phil\PagedMediaBundle\Infrastructure\Template\*Template` | When `templates.enabled` is true: the eight printed templates, tagged under their names, on the fonts of `templates` |
| `Phil\PagedMediaBundle\Infrastructure\Storage\JobCodec` | Always: the JSON a job store keeps |
| `Phil\PagedMediaBundle\Application\PagedMediaQueue` | When Symfony Messenger is installed: public, on `MessageBusInterface` |
| `Phil\PagedMediaBundle\Infrastructure\Messenger\PagedMediaHandler` | When Symfony Messenger is installed |
| `Phil\PagedMediaBundle\Infrastructure\Messenger\FailedJobPurger` | When Symfony Messenger is installed and `jobs.remove_failed` is true: listens to `WorkerMessageFailedEvent` and `SyncMessageFailedEvent` |

A class implementing `DocumentTemplate` with `#[AsDocumentTemplate('name')]` is tagged by autoconfiguration; without
autoconfiguration, tag it `phil_paged_media.template` with a `name`.
