<!-- phil-dev source-sha256=92f5ee105038fe9d75535beb1dcd5ea838cac39656c9c66c393547464bbf0bf3 -->
# Importer

## Dans un contrôleur

```php
use Phil\DocumentImport\Exception\DocumentImportException;
use Phil\DocumentImportBundle\Application\DocumentImports;
use Phil\Ujo\Ujo;

#[Route('/manuscripts/import', methods: ['POST'])]
public function import(Request $request, DocumentImports $imports): Response
{
    $file = $request->files->get('manuscript');

    try {
        $imported = $imports->importFile($file->getPathname(), filename: $file->getClientOriginalName());
    } catch (DocumentImportException $exception) {
        return $this->render('manuscripts/refused.html.twig', ['reason' => $exception->getMessage()], new Response(status: 422));
    }

    $manuscript->replaceText(new Ujo()->encode($imported->document));

    return $this->render('manuscripts/imported.html.twig', ['report' => $imported->report->summary()]);
}
```

Le chemin temporaire d'un téléversement n'a pas d'extension : `importFile()` décompose le nom de fichier du client pour
détecter le format à partir de lui et des octets. Ce nom est celui du client : un format qui n'est pas dans
`formats.import` est refusé quoi que dise le nom, et un fichier composé est refusé quel que soit son nom. Un fichier plus
grand que `limits.total_bytes` est refusé avant d'être lu en mémoire, et des octets plus grands que cette limite sont
refusés quel que soit leur format, Markdown et HTML compris.

`DocumentImports` ne conserve aucun état ; un seul service importe tous les fichiers. `DocumentImporter` reste
disponible pour le code qui a besoin de tous les formats que lit la bibliothèque.

## Analysé avant d'être lu

Lorsque `phil/upload-scan-bundle` est enregistré, `DocumentImports` remet chaque fichier à son analyseur une fois la
taille et le format acceptés et avant qu'un lecteur l'ouvre, sous le contexte `document_import` : l'antivirus regarde à
l'intérieur de l'archive d'un DOCX ou d'un ODT. Un fichier infecté lève `InvalidDocument` — « The document contains a
threat and was refused. » — et est mis en quarantaine par ce bundle ; un fichier que l'analyseur n'a pas pu vérifier lève
`InvalidDocument`, à moins que la politique de l'analyseur l'accepte. `scan` le met à `on` — obligatoire, refusé au
démarrage sans le bundle — ou à `off`.

L'analyse préalable est le service `Phil\DocumentImportBundle\Application\Contract\DocumentScreening` : une application
qui a une autre vérification fait pointer l'alias de l'interface vers son propre service, et définit `scan: off` pour que
le bundle n'en enregistre aucune.

## Le rapport

`Imported::$report` énumère ce que le document contenait et qu'un document UJO (Universal JSON Output) ne conserve pas —
commentaires, suppressions suivies, couleurs, objets incorporés, annotations d'une page PDF, paragraphes des styles que
`styles` laisse de côté — ainsi que les parties d'images SVG que l'assainisseur a retirées, sous `svg-content`, avec la
raison de chaque retrait comme détail. Une importation avec des omissions a réussi. L'application montre le rapport de
l'une de ces façons :

- `Report::summary()`, une phrase par genre, en anglais ; `Report::toArray()`, des lignes de `what`, `detail`, `count` et
  `description`, à traduire selon la valeur de chaque ligne ;
- `DocumentImports::documentWithReport($imported)`, le document avec un dernier bloc `warning` intitulé par
  `report.title`, pour qu'un éditeur montre le rapport avec le texte ; le document seul lorsque rien n'a été laissé de
  côté ;
- `@PhilDocumentImport/report.html.twig`, un fragment HTML, lorsque Twig est installé ;
- un écouteur de `Phil\DocumentImportBundle\Domain\Event\DocumentImported`, réparti après chaque importation de
  `DocumentImports` lorsque l'application a un répartiteur d'événements, avec le résultat, le nom du fichier et, pour une
  importation sur un *worker*, sa référence.

```php
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ImportReports
{
    public function __invoke(DocumentImported $event): void
    {
        foreach ($event->imported->report->toArray() as $row) {
            // count the omissions, keep them with the manuscript, tell the author
        }
    }
}
```

### Le rapport dans Twig

```twig
{% include '@PhilDocumentImport/report.html.twig' with {report: imported.report} %}
```

Le fragment produit une `section` de rôle `status`, un titre — `report.title`, par la fonction Twig
`document_import_report_title()`, à moins que `title` soit donné — et un élément de liste par phrase de
`Report::summary()`, échappée. Il ne produit rien lorsque rien n'a été laissé de côté. `level` définit le niveau du
titre, de 1 à 6, 2 par défaut ; `id` l'identifiant du titre, dont la section porte l'étiquette,
`document-import-report-title` par défaut. Le thème de l'application met en forme la classe `document-import-report`.

```twig
{% include '@PhilDocumentImport/report.html.twig' with {report: imported.report, title: 'Not kept'|trans, level: 3, id: 'chapter-' ~ chapter.id ~ '-report'} %}
```

## La table des styles

`styles` décide de ce que devient un style de paragraphe avant les règles propres aux lecteurs : un style maison
`Chapter Title` lu comme un titre de niveau 1, `Rules Box` comme une citation, `Author Note` laissé de côté. Un style
fondé sur un style nommé devient ce que devient son parent, à moins d'être nommé lui aussi. DOCX, ODT, ODT plat et RTF
ont des styles de paragraphe ; Markdown, HTML et PDF n'en ont pas.

## Exporter

```php
$exported = $imports->export($manuscript->document(), Format::Docx, ['title' => $manuscript->title()]);

return new Response($exported->bytes, headers: [
    'Content-Type' => $exported->mediaType(),
    'Content-Disposition' => HeaderUtils::makeDisposition('attachment', 'manuscript.'.$exported->extension()),
]);
```

L'exportateur écrit du DOCX ou de l'ODT, parmi `formats.export`, et lit les images dans `export.media_source` ; il ne va
jamais chercher une adresse lui-même. Une image SVG entre dans l'ODT une fois assainie par le service `SvgSanitizer`.
`Exported::$report` énumère les blocs et les images pour lesquels le fichier n'a pas de place.

## Longs documents

Une grande archive ou un grand PDF est lu en mémoire et ses images sont stockées une par une : importer un livre est une
tâche pour un *worker* de Messenger plutôt que pour une requête. [Importations sur un worker](worker.md) met le fichier
en file avec un message qui ne porte que sa référence.
