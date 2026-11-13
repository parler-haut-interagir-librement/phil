<!-- phil-dev source-sha256=ce0b1edf88fc8807250760ab2d496bfdc8d29432e1722e477d646875f716d4b2 -->
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

Un certificat de type `file` a besoin de son `path`, un certificat `inline` de son `certificate`, un certificat
`vault` de son adresse, de son jeton et de son chemin ; le conteneur refuse un certificat qui ne les a pas. Gardez les
secrets hors du fichier : `%env(SIGNING_PASSWORD)%`, `%env(SIGNING_CERTIFICATE)%` depuis le coffre de secrets de
Symfony, `%env(VAULT_TOKEN)%`.

## Services

| Service | Quand |
|---|---|
| `Phil\PagedMediaBundle\Application\PagedMedia` | Toujours : le service unique, public |
| `Phil\PagedMedia\Gotenberg\Gotenberg` | Quand `gotenberg.url` est défini, sur le service `http_client` |
| `Phil\PagedMedia\Gotenberg\Signing\CertificateSource` | Quand `signing.certificate.type` ne vaut pas `none` : `FileCertificateSource`, `InlineCertificateSource` ou `VaultCertificateSource` |
| `Phil\PagedMedia\Gotenberg\Signing\TimestampAuthority` | Quand `signing.timestamp_url` est défini |
| `Phil\PagedMediaBundle\Application\DocumentTemplates` | Toujours : les services étiquetés `phil_paged_media.template`, par nom |
| `Phil\PagedMediaBundle\Application\Port\PdfStorage` | Toujours : un alias de `DirectoryStorage` sur `storage.directory` ; faites-en l'alias d'un de vos services pour garder les fichiers ailleurs |
| `Phil\PagedMediaBundle\Application\Port\JobStore` | Toujours : un alias de `DirectoryJobStore` sur `jobs.directory`, ou de `CacheJobStore` sur le pool que nomme `jobs.cache_pool` ; faites-en l'alias d'un de vos services pour garder les tâches ailleurs |
| `Phil\PagedMediaBundle\Infrastructure\Template\*Template` | Quand `templates.enabled` vaut true : les huit gabarits d'imprimés, étiquetés sous leurs noms, avec les polices de `templates` |
| `Phil\PagedMediaBundle\Infrastructure\Storage\JobCodec` | Toujours : le JSON que garde un stockage de tâches |
| `Phil\PagedMediaBundle\Application\PagedMediaQueue` | Quand Symfony Messenger est installé : public, sur `MessageBusInterface` |
| `Phil\PagedMediaBundle\Infrastructure\Messenger\PagedMediaHandler` | Quand Symfony Messenger est installé |
| `Phil\PagedMediaBundle\Infrastructure\Messenger\FailedJobPurger` | Quand Symfony Messenger est installé et que `jobs.remove_failed` vaut true : écoute `WorkerMessageFailedEvent` et `SyncMessageFailedEvent` |

Une classe qui implémente `DocumentTemplate` avec `#[AsDocumentTemplate('name')]` est étiquetée par la configuration
automatique. Sans configuration automatique, étiquetez-la `phil_paged_media.template` avec un `name`.
