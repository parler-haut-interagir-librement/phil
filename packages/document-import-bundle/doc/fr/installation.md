<!-- phil-dev source-sha256=fab761225121a36bf506c199d66e8cac1a5fc14075a9677ddf28f83b65e4accc -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Comme `phil/document-import` |
| `ext-mbstring` | Le texte de remplacement d'un média coupé en caractères |
| `phil/document-import` | L'importateur et l'exportateur |
| `phil/svg-sanitizer` | L'assainisseur des images SVG |
| `phil/ujo` | Les documents importés et exportés |
| `psr/event-dispatcher` | L'interface par laquelle `DocumentImported` est réparti |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La configuration et les services |
| `ext-sodium` | Les références des fichiers qui attendent le *worker* |
| `phil/media-bundle`, facultatif | Les images conservées dans la médiathèque |
| `phil/upload-scan-bundle`, facultatif | Chaque fichier analysé par un antivirus avant d'être lu |
| `symfony/messenger`, facultatif | Les importations sur un *worker* |
| `twig/twig`, facultatif | Le rapport d'une importation comme fragment HTML |

## Installer

```sh
composer require phil/document-import-bundle
```

Symfony Flex active le bundle ; sans Flex, ajoutez-le à `config/bundles.php` :

```php
return [
    // ...
    Phil\DocumentImportBundle\PhilDocumentImportBundle::class => ['all' => true],
];
```

Sans configuration, `DocumentImports` lit tous les formats de la bibliothèque et écrit du DOCX et de l'ODT, avec les
limites et la politique SVG des bibliothèques et une table des styles vide, et conserve les images dans la médiathèque
lorsque `PhilMediaBundle` est enregistré, ou répond sinon `media/` suivi de leur nom de fichier, et analyse chaque
fichier avant de le lire lorsque `PhilUploadScanBundle` est enregistré. Le rapport de chaque importation est réparti
lorsque l'application a un répartiteur d'événements, comme c'est le cas d'une application Symfony complète.
