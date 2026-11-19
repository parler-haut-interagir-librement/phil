<!-- phil-dev source-sha256=b002709fcbfe0150e75be1618c1400643d27e6b36e483dd57699a419a557346c -->
# phil/document-import-bundle

**Des documents de traitement de texte et des PDF importés dans une application Symfony, leurs images assainies et
conservées dans la médiathèque, et des documents exportés en DOCX ou en ODT.**

phil/document-import-bundle installe `phil/document-import` dans une application Symfony. Il enregistre le
`DocumentImporter` et le `DocumentExporter` comme services, avec les limites, la table des styles et la politique du
`SvgSanitizer` par lequel passe chaque image SVG. Il donne aussi à l'importateur un magasin de médias : la médiathèque
de `phil/media-bundle` lorsque ce bundle est enregistré — chaque image téléversée comme média, dans une collection de
la configuration, avec son texte de remplacement —, sinon un préfixe de dossier. `DocumentImports` ne lit que les
formats que l'application accepte — DOCX (OOXML strict compris), ODT, ODT plat, RTF, PDF, Markdown, HTML —, refuse un
téléversement plus grand que les limites avant de le lire, fait analyser chaque fichier par `phil/upload-scan-bundle`
avant de l'analyser lorsque ce bundle est enregistré, et donne le rapport de ce que chaque importation a laissé de côté
sous la forme d'un événement, d'un bloc du document ou d'un fragment Twig. `DocumentImportQueue` importe sur un *worker*
de Messenger, avec des messages qui ne portent qu'une référence et rien d'autre : le fichier attend dans un magasin,
jamais dans le message.

Il s'adresse à toute application Symfony qui reçoit des textes écrits ailleurs : un CMS qui accepte des manuscrits, un
outil qui met en page des livres à partir des chapitres de leurs auteurs, un wiki qui importe d'anciennes pages. Un
contrôleur demande l'importateur, importe le fichier téléversé et obtient un document UJO (Universal JSON Output) dont
les blocs d'image pointent vers des médias déjà conservés.

C'est un bundle mince au-dessus de `symfony/config` et de `symfony/dependency-injection`, qui répartit ses événements par
`psr/event-dispatcher`. `phil/media-bundle`, `phil/upload-scan-bundle`, `symfony/messenger` et Twig sont facultatifs :
chaque partie qui en utilise un n'est enregistrée que lorsqu'il est installé ou activé.

La conception est petite : `PhilDocumentImportBundle` lit la configuration, `DocumentImportServices` enregistre les
services, `DocumentImports` applique les formats acceptés et l'analyse préalable et répartit `DocumentImported`,
`DocumentImportQueue` et `ImportDocumentHandler` importent sur un *worker*, et `MediaLibraryStore` conserve les images
dans la médiathèque.

## État

| Partie | État |
|---|---|
| `DocumentImporter`, `DocumentExporter` et `SvgSanitizer` comme services ; les limites, la table des styles et la politique SVG configurées | Implémenté et testé |
| `DocumentImports` : les formats acceptés à l'importation et à l'exportation, les téléversements vérifiés contre les limites, le titre du rapport | Implémenté et testé |
| `DocumentImported`, le rapport de chaque importation réparti comme événement | Implémenté et testé |
| Les images conservées dans `phil/media-bundle`, dans une collection configurée, avec leur texte de remplacement | Implémenté et testé |
| Les images sous un préfixe de dossier, sans la médiathèque | Implémenté et testé |
| Chaque fichier analysé par `phil/upload-scan-bundle` avant d'être lu | Implémenté et testé |
| Le rapport comme fragment Twig, `@PhilDocumentImport/report.html.twig` | Implémenté et testé |
| Des importations sur un *worker* de Messenger, le message portant une référence, le fichier dans un répertoire ou un magasin de l'application | Implémenté et testé |
| Un formulaire de téléversement et un contrôleur qui importe | Non commencé : relève de l'application |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'activation du bundle. |
| [Configuration](doc/fr/configuration.md) | Chaque option, et les services qu'elle donne. |
| [Médias](doc/fr/media.md) | L'endroit où vont les images : la médiathèque, un dossier, un magasin propre à l'application. |
| [Importer](doc/fr/importing.md) | Importer dans un contrôleur, l'analyse avant la lecture, le rapport montré à l'auteur, la table des styles, l'exportation. |
| [Importations sur un worker](doc/fr/worker.md) | La file, son message et ses références, l'endroit où attendent les fichiers, les événements du résultat. |
| [Points d'extension](doc/fr/extension-points.md) | Les services et les options qu'une application remplace ou utilise. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lèvent le bundle et les bibliothèques. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et le test d'une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/document-import-bundle
```

```yaml
# config/packages/phil_document_import.yaml
phil_document_import:
    media:
        collection: manuscripts
    formats:
        import: [docx, odt, rtf]
    styles:
        'Chapter Title': heading-1
        'Author Note': omit
    svg:
        max_bytes: 1048576
```

```php
public function __invoke(Request $request, DocumentImports $imports): Response
{
    $file = $request->files->get('manuscript');
    $imported = $imports->importFile($file->getPathname(), filename: $file->getClientOriginalName());
    // $imported->document: the UJO document; $imported->report: what was left out;
    // $imports->documentWithReport($imported): the document with the report as a last block.
}
```

```twig
{% include '@PhilDocumentImport/report.html.twig' with {report: imported.report} %}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-sodium`, Symfony 8.2, `phil/document-import`, `phil/svg-sanitizer`, `phil/ujo` et
`psr/event-dispatcher` ; `phil/media-bundle`, pour conserver les images dans la médiathèque ; `phil/upload-scan-bundle`,
pour l'antivirus ; `symfony/messenger`, pour les importations sur un *worker* ; Twig, pour le fragment du rapport.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
