<!-- phil-dev source-sha256=074d1fc719064e7304895daa5ef3e23afebbd5511e29dad6fa20f09bd86fc060 -->
# phil/upload-scan-bundle

**Des téléversements analysés par ClamAV avant d'être conservés, et des fichiers infectés mis en quarantaine là où
personne ne peut les télécharger.**

phil/upload-scan-bundle intègre `phil/upload-scan` à une application Symfony. L'analyseur antivirus — clamd, l'analyseur
factice des tests ou celui de l'application — et la politique appliquée à un fichier qui n'a pas pu être analysé
viennent de la configuration. Une contrainte `#[CleanFile]` analyse un `UploadedFile` pendant la validation d'un
formulaire ou d'un DTO. Un fichier infecté est copié dans une quarantaine hors de la racine Web, sous un identifiant
aléatoire, et annoncé par un événement `FileInfected`. Un opérateur lit la quarantaine et supprime les fichiers traités
sur une page d'administration, liée depuis un menu EasyAdmin, qui ne montre jamais ce qu'un fichier contient. Un fichier
accepté sans analyse sous la politique
`accept_and_flag` est annoncé par `FileFlagged`. Un message `RescanFile`, qui ne transporte que des identifiants,
l'analyse de nouveau sur un *worker*, acheminé vers le transport de la configuration et suivi de `FileRescanned`. Une
commande de la console supprime les fichiers en quarantaine conservés plus longtemps que la durée de rétention.

Quand leurs bundles sont enregistrés, les originaux de `phil/media-bundle` sont analysés avant d'être stockés et
`phil/monitoring-bundle` indique si l'antivirus répond ; `phil/document-import-bundle` analyse les documents qu'il lit
par l'`UploadScanner` de ce bundle.

Il est publié sous licence MIT.

## État

| Partie | État |
|---|---|
| L'analyseur, la politique et la quarantaine tirés de la configuration | Implémentés et testés |
| La contrainte `#[CleanFile]` | Implémentée et testée |
| La quarantaine dans un répertoire hors de `public/`, `FileInfected` et `FileFlagged` | Implémentés et testés |
| `RescanFile` et son *handler*, par-dessus le `RescanTarget` de l'application | Implémentés et testés |
| `phil/media-bundle` et `phil/monitoring-bundle` | Implémentés et testés |
| La page d'administration de la quarantaine, `QuarantineInventory`, le lien du menu EasyAdmin | Implémentés et testés |
| La rétention de la quarantaine et `phil:upload-scan:purge-quarantine` | Implémentées et testées |
| L'acheminement de `RescanFile` vers un transport, `FileRescanned` | Implémentés et testés |
| Un téléversement suivi jusqu'à la quarantaine et sa page dans une application démarrée | Implémenté et testé sur l'analyseur factice |
| L'analyseur clamd contre un vrai clamd | `ClamdTest` de `phil/upload-scan` et le groupe `clamd` du bundle, lancés quand `PHIL_UPLOAD_SCAN_CLAMD` nomme un clamd |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, une première analyse. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_upload_scan`. |
| [ClamAV](doc/fr/clamav.md) | Un service clamd à côté de l'application dans Docker Compose. |
| [Validation](doc/fr/validation.md) | La contrainte `#[CleanFile]` et le `UploadScanner`. |
| [Quarantaine et événements](doc/fr/quarantine-and-events.md) | Où vont les fichiers infectés, la page d'administration, la rétention, `FileInfected` et `FileFlagged`. |
| [CSRF](doc/fr/csrf.md) | Le jeton que la page d'administration exige, son envoi, et `admin.enabled`. |
| [Nouvelles analyses](doc/fr/rescans.md) | `RescanFile`, `RescanTarget`, le *worker* et `FileRescanned`. |
| [Intégrations](doc/fr/integrations.md) | La médiathèque, l'importateur de documents, le contrôle de santé. |
| [Points d'extension](doc/fr/extension-points.md) | L'analyseur, la quarantaine et les cibles de nouvelle analyse de l'application. |
| [Correspondance avec ASVS 5.0](doc/fr/asvs.md) | Les exigences que le bundle satisfait, les tests qui les démontrent et celles qui reviennent à l'application. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui analyse. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/upload-scan-bundle
```

```yaml
# config/packages/phil_upload_scan.yaml
phil_upload_scan:
    clamd:
        address: tcp://clamav:3310
```

```php
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class ManuscriptUpload
{
    #[Assert\File(maxSize: '20M')]
    #[CleanFile(context: 'manuscript')]
    public ?UploadedFile $file = null;
}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, `ext-sodium`, et un clamd que l'application peut joindre.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
