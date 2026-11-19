<!-- phil-dev source-sha256=c52f8e7a77de74eb8be814a32877b511d8d0590d2c6bbec0dcc17fabec6a9f2c -->
# Table des styles

Un traitement de texte nomme le rôle d'un paragraphe par son style. L'importateur connaît les noms que les traitements
de texte donnent à leurs titres, citations et blocs de code (voir [Documents de traitement de texte](word-processing.md)),
mais le gabarit d'un éditeur a les siens : `Chapter Title`, `Rules Box`, `Author Note`. Une `StyleMap` dit à
l'importateur ce que chacun devient.

```php
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\StyleMap;
use Phil\DocumentImport\StyleRole;

$importer = new DocumentImporter(styles: new StyleMap([
    'Chapter Title' => StyleRole::Heading1,
    'Section' => StyleRole::Heading2,
    'Rules Box' => StyleRole::Quote,
    'Listing' => StyleRole::Code,
    'Author Note' => StyleRole::Omit,
    'Heading 1' => StyleRole::Paragraph,
]));

$map = new StyleMap()->with('Sidebar', StyleRole::Quote);
```

| `StyleRole` | Bloc |
|---|---|
| `Paragraph` | `paragraph` |
| `Heading1` à `Heading6` | `header` de ce niveau |
| `Quote` | `quote` |
| `Code` | `code`, les paragraphes consécutifs du style joints |
| `Omit` | Rien : le paragraphe est laissé de côté et signalé comme `style`, avec le nom du style |

## Comment les styles sont associés

Un style est nommé comme le montre le traitement de texte (`Chapter Title`, `Heading 1`) ou par son identifiant dans le
fichier : `ChapterTitle` dans un DOCX, `Chapter_20_Title` dans un ODT ; un style RTF, par son nom seulement. La casse et
les espaces autour du nom n'importent pas.

La table est lue avant les règles propres de l'importateur. Le style d'un paragraphe est cherché d'abord, puis le style
dont il est dérivé (`w:basedOn`, `style:parent-style-name`, `\sbasedon`), et le premier nom que la table contient
décide ; ce n'est que quand la table n'en contient aucun que les noms intégrés et les niveaux de plan s'appliquent. Dans
un DOCX ou un RTF, un paragraphe dont la table nomme le style n'est jamais un élément de liste, même quand le style
porte une numérotation ; dans un ODT, un titre (`text:h`) dont la table nomme le style prend le bloc de la table, tandis
que les paragraphes d'une liste restent ses éléments.

## Formats

DOCX, DOCX strict, ODT, ODT plat et RTF lisent la table. Le Markdown et le HTML ne portent aucun style de paragraphe, et
un PDF n'en a pas : la table ne s'applique pas à eux.

La table ne contient que des noms : c'est une valeur, construite une fois et partagée par chaque importation.
