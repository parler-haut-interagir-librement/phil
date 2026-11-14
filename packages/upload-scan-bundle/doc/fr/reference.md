<!-- phil-dev source-sha256=92681836b1b9fc114c5287477d0fbe79433179f0b45258fbb213cb05fa92ba9d -->
# Référence

## Types

| Type | Nature | Rôle |
|---|---|---|
| `Phil\UploadScanBundle\PhilUploadScanBundle` | bundle | La configuration et le câblage : `RESCAN_TARGET_TAG` |
| `Phil\UploadScanBundle\Application\UploadScanner` | `final readonly class` | L'inspection d'un fichier : `inspectPath()`, `inspectContents()`, `inspectStream()` |
| `Phil\UploadScanBundle\Application\Inspection` | `final readonly class` | Le résultat, la décision et l'identifiant de quarantaine : `accepted()`, `flagged()`, `infected()` |
| `Phil\UploadScanBundle\Application\Contract\Quarantine` | interface | L'endroit où sont conservés les fichiers infectés : `isolate()` |
| `Phil\UploadScanBundle\Application\Contract\QuarantineInventory` | interface | Ce qu'un opérateur lit et supprime : `files()`, `file()`, `delete()` |
| `Phil\UploadScanBundle\Application\QuarantinedFile` | `final readonly class` | La fiche d'un fichier en quarantaine : identifiant, nom, signature, date, taille |
| `Phil\UploadScanBundle\Application\Contract\RescanTarget` | interface | Des fichiers analysés de nouveau par identifiant : `name()`, `open()`, `resolve()` |
| `Phil\UploadScanBundle\Application\Event\FileInfected` | `final readonly class` | Un fichier infecté refusé |
| `Phil\UploadScanBundle\Application\Event\FileFlagged` | `final readonly class` | Un fichier accepté sans analyse |
| `Phil\UploadScanBundle\Application\Event\FileRescanned` | `final readonly class` | Un fichier analysé de nouveau sur un *worker* : `cleared()` |
| `Phil\UploadScanBundle\Application\QuarantinePurger` | `final readonly class` | La rétention de la quarantaine : `purge()`, `PAGE_SIZE`, `DEFAULT_MAX` |
| `Phil\UploadScanBundle\Infrastructure\Console\PurgeQuarantineCommand` | commande de la console | `phil:upload-scan:purge-quarantine` : `NAME` |
| `Phil\UploadScanBundle\Application\Message\RescanFile` | `final readonly class` | Une cible et un identifiant de fichier |
| `Phil\UploadScanBundle\Application\RescanFileHandler` | `final readonly class` | Traite `RescanFile` |
| `Phil\UploadScanBundle\Application\Exception\QuarantineFailed` | `final class` | Un fichier que la quarantaine n'a pas pu conserver |
| `Phil\UploadScanBundle\Application\Exception\UnknownRescanTarget` | `final class` | Un message qui ne nomme aucune cible |
| `Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine` | `final readonly class` | La quarantaine dans un répertoire local, et son inventaire |
| `Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController` | contrôleur | La page de la quarantaine : `LIST_ROUTE`, `DELETE_ROUTE`, `CSRF_TOKEN_ID` |
| `Phil\UploadScanBundle\Infrastructure\EasyAdmin\QuarantineAdmin` | `final class` | Le lien d'un menu EasyAdmin vers la page de la quarantaine |
| `Phil\UploadScanBundle\Infrastructure\DependencyInjection\QuarantineInventoryPass` | passe du compilateur | `QuarantineInventory` donné à la quarantaine quand elle peut être listée ; la page et la purge retirées sans inventaire |
| `Phil\UploadScanBundle\Infrastructure\Validator\CleanFile` | contrainte | Un fichier exempt de menaces connues : `INFECTED_ERROR`, `NOT_SCANNED_ERROR` |
| `Phil\UploadScanBundle\Infrastructure\Validator\CleanFileValidator` | validateur | Vérifie `CleanFile` |
| `Phil\UploadScanBundle\Infrastructure\Media\ScanningMediaStorage` | `final readonly class` | Le stockage des médias derrière l'antivirus |
| `Phil\UploadScanBundle\Infrastructure\Monitoring\ScannerHealthCheck` | `final readonly class` | Le contrôle de santé `upload_scan` |

## Services

| Identifiant | Enregistré |
|---|---|
| `Phil\UploadScan\Contract\Scanner` | Toujours : un alias de l'analyseur choisi. |
| `Phil\UploadScan\Clamd\ClamdScanner`, `FakeScanner`, `NullScanner` | Celui que nomme `scanner`. |
| `Phil\UploadScan\ScanPolicy` | Toujours, avec `on_error`. |
| `Phil\UploadScanBundle\Application\Contract\Quarantine` | Sauf si la quarantaine est désactivée : un alias de `FilesystemQuarantine` ou de `quarantine.service`. |
| `Phil\UploadScanBundle\Application\Contract\QuarantineInventory` | Quand la quarantaine l'implémente, sauf si l'application en fait un alias. |
| `Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController` | Avec une quarantaine, Twig et `symfony/security-csrf`, et un inventaire ; public, avec l'étiquette `controller.service_arguments`. |
| `Phil\UploadScanBundle\Application\QuarantinePurger`, `Phil\UploadScanBundle\Infrastructure\Console\PurgeQuarantineCommand` | Avec une quarantaine, le composant Console et un inventaire ; la commande avec l'étiquette `console.command`. |
| `Phil\UploadScanBundle\Application\UploadScanner` | Toujours. |
| `Phil\UploadScanBundle\Infrastructure\Validator\CleanFileValidator` | Toujours, avec l'étiquette `validator.constraint_validator`. |
| `Phil\UploadScanBundle\Application\RescanFileHandler` | Avec Messenger, avec l'étiquette `messenger.message_handler` ; `RescanFile` acheminé vers `rescan.transport` quand elle en nomme un. |
| `Phil\UploadScanBundle\Infrastructure\Media\ScanningMediaStorage` | Avec `phil/media-bundle`, en décorant `MediaStorage`. |
| `Phil\UploadScanBundle\Infrastructure\Monitoring\ScannerHealthCheck` | Avec `phil/monitoring-bundle` et l'analyseur `clamd` ou `fake`. |
