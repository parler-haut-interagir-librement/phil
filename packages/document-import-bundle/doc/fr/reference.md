<!-- phil-dev source-sha256=896233a6fa9e5e3df378c719bc2f0622db5a95091c0fc9a1a49e55c3aaa268e4 -->
# Référence

| Classe | Rôle |
|---|---|
| `Phil\DocumentImportBundle\PhilDocumentImportBundle` | Le bundle : la configuration `phil_document_import` et ses services |
| `Phil\DocumentImportBundle\Application\DocumentImports` | Importe les formats acceptés, refuse un fichier plus grand que les limites avant de le lire et des octets plus grands qu'elles dans tous les formats, analyse chaque fichier avant de le lire, répartit `DocumentImported`, ajoute le rapport sous le titre configuré, exporte les formats acceptés |
| `Phil\DocumentImportBundle\Application\DocumentImportQueue` | Accepte un fichier comme le ferait `DocumentImports`, le garde dans `PendingImports` et répartit `ImportDocument` avec sa référence |
| `Phil\DocumentImportBundle\Application\ImportDocumentHandler` | Traite `ImportDocument` : importe le fichier gardé sous la référence, répartit `DocumentImported` ou `DocumentImportFailed`, libère la référence |
| `Phil\DocumentImportBundle\Application\PendingImport` | Un fichier qui attend le *worker* : `$contents`, `$format`, `$filename` |
| `Phil\DocumentImportBundle\Application\Message\ImportDocument` | Le message d'une importation sur un *worker* : `$claim`, de 1 à 255 lettres, chiffres, `.`, `_` ou `-` (`ImportDocument::CLAIM`) |
| `Phil\DocumentImportBundle\Application\Contract\DocumentScreening` | `screen(string $contents, string $filename): void`, qui lève `InvalidDocument` pour refuser un fichier |
| `Phil\DocumentImportBundle\Application\Contract\PendingImports` | `keep(PendingImport): string`, `take(string $claim): ?PendingImport`, `release(string $claim): void` |
| `Phil\DocumentImportBundle\Domain\Event\DocumentImported` | L'événement d'une importation : `$imported`, le résultat et son rapport, `$filename`, le nom avec lequel le fichier est venu, et `$claim`, la référence d'une importation sur un *worker* |
| `Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed` | Un fichier importé sur un *worker* et refusé : `$claim`, et `$reason`, la `DocumentImportException` |
| `Phil\DocumentImportBundle\Infrastructure\DependencyInjection\DocumentImportServices` | Enregistre les services ; leurs identifiants en constantes |
| `Phil\DocumentImportBundle\Infrastructure\Media\MediaLibraryStore` | Le `MediaStore` de `phil/document-import` au-dessus de la médiathèque de `phil/media-bundle` |
| `Phil\DocumentImportBundle\Infrastructure\UploadScan\UploadScanScreening` | Le `DocumentScreening` au-dessus de l'analyseur de `phil/upload-scan-bundle`, sous le contexte `document_import` |
| `Phil\DocumentImportBundle\Infrastructure\Claim\FilesystemPendingImports` | Le `PendingImports` au-dessus d'un répertoire, ses références étant 32 chiffres hexadécimaux aléatoires |
| `Phil\DocumentImportBundle\Infrastructure\Twig\ImportReportExtension` | La fonction Twig `document_import_report_title()` |

| Méthode de `DocumentImports` | Retourne |
|---|---|
| `importFile(string $path, ?Format $format = null, ?string $filename = null)` | `Imported` ; le format donné, ou détecté à partir de `$filename` — le nom de base du chemin par défaut — et des octets |
| `import(string $contents, ?Format $format = null, ?string $filename = null, ?string $claim = null)` | `Imported` ; `$claim` est donné à `DocumentImported` |
| `accept(string $contents, ?Format $format = null, ?string $filename = null)` | Le `Format` d'un fichier que l'application accepte, par sa seule taille et son seul format, sans rien lire |
| `reportTitle()` | `report.title` |
| `documentWithReport(Imported $imported)` | Le `Document` avec le rapport comme dernier bloc `warning` intitulé par `report.title`, ou seul lorsque rien n'a été laissé de côté |
| `export(Document $document, Format $format, array $metadata = [])` | `Exported` |

| Méthode de `DocumentImportQueue` | Retourne |
|---|---|
| `submit(string $contents, ?Format $format = null, ?string $filename = null)` | La référence que portera le résultat |

| Constante | Valeur |
|---|---|
| `PhilDocumentImportBundle::EXPORTS` | `['docx', 'odt']`, les formats qu'une exportation peut écrire |
| `DocumentImportServices::SVG_POLICY` | `phil_document_import.svg_policy` |
| `DocumentImportServices::MEDIA_ADDRESS` | `phil_document_import.media_address` |

Les services sont énumérés dans [Configuration](configuration.md).
