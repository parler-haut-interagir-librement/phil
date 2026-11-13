<!-- phil-dev source-sha256=22c6e5b4c8a38bd8aacb41dc7f9a4037194966014509fd28911d57358a19c43d -->
# LibreOffice

`Gotenberg::convert()` convertit en PDF, avec LibreOffice, les formats Word, Excel, PowerPoint, OpenDocument et plus
d'une centaine d'autres, puis exécute une liste d'étapes sur les PDF quand on lui en donne une.

```php
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\LibreOffice\PaperSize;

$result = $gotenberg->convert(
    Files::of(InputFile::fromPath('report.docx')),
    new LibreOfficeOptions(paperSize: PaperSize::A4, nativePdfa: Profile::PdfA2b, nativePdfua: Profile::PdfUa1),
    new Pipeline(new SetMetadata(title: 'Annual report')),
);
$result->filename;   // report.docx.pdf
```

Un PDF sort par document, nommé d'après lui ; plusieurs reviennent dans un ZIP, sauf si une étape `merge` les réunit,
dans l'ordre alphanumérique de leurs noms.

## Options

`LibreOfficeOptions` accepte les noms d'options de Gotenberg ; une option non réglée garde la valeur par défaut de
LibreOffice et n'est pas envoyée.

| Groupe | Options |
|---|---|
| Pages | `password`, `paperSize` (`PaperSize`), `paperWidth`, `paperHeight` (en pouces, ou un nombre avec `mm`, `cm`, `in`, `pt`, `px`, `pc`), `landscape`, `nativePages`, `singlePage`, `skipEmptyPages` |
| Contenu | `updateIndexes`, `exportFormFields`, `allowDuplicateFieldNames`, `nativeBookmarks`, `exportBookmarksToPdfDestination`, `exportPlaceholders`, `exportNotes`, `exportNotesInMargin`, `exportNotesPages`, `exportOnlyNotesPages`, `exportHiddenSlides`, `useTransitionEffects`, `convertOooTargetToPdfTarget`, `exportLinksRelativeFsys`, `addOriginalDocumentAsStream` |
| Images | `nativeQuality` (de 1 à 100), `nativeLosslessImageCompression`, `nativeReduceImageResolution`, `nativeMaxImageResolution` (75, 150, 300, 600 ou 1200) |
| Filigrane | `nativeWatermarkText` ou `nativeTiledWatermarkText`, `nativeWatermarkColor` (`#rrggbb`), `nativeWatermarkFontSize`, `nativeWatermarkRotation`, `nativeWatermarkFont` |
| Conformité | `nativePdfa` (PDF/A-1b, PDF/A-2b, PDF/A-3b ou PDF/A-4), `nativePdfua` (PDF/UA-1), `nativeTaggedPdf` |
| Lecteur | `initialView` (`InitialView`), `initialPage`, `magnification` (`Magnification`), `zoom`, `pageLayout` (`PageLayout`), `openBookmarkLevels` (de 1 à 10, ou -1), `resizeWindowToInitialPage`, `centerWindow`, `openInFullScreenMode`, `displayPDFDocumentTitle`, `hideViewerMenubar`, `hideViewerToolbar`, `hideViewerWindowControls` |

Les options refusent ce que Gotenberg refuserait : une largeur sans hauteur quand aucun format de papier n'est donné,
les deux filigranes, un niveau PDF/A que LibreOffice n'exporte pas, et PDF/A-4 avec PDF/UA, que LibreOffice prétend
produire sans y parvenir.

## Options natives ou étapes

Une option dont le nom commence par `native` s'adresse à LibreOffice lui-même. Une étape fait le même travail sur le
PDF ensuite, de la même façon pour toutes les routes, et va plus loin : `stamp-text` pour `nativeWatermarkText`,
`remediate` pour `nativePdfa` et `nativePdfua`, qui atteint tous les niveaux PDF/A et rapporte les violations
restantes. Rien ne vérifie la sortie native, et une étape après elle peut briser sa conformité : une étape `remediate`
placée en dernier la vérifie.
