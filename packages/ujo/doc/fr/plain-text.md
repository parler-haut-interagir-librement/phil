<!-- phil-dev source-sha256=888ce7e25fdc7154d90f62ebd7ef41ed7e5bb3a0e08d1367b48a6a8312cb5e9e -->
# Texte brut

`PlainText::convert($document)` donne le texte d'un document (pour un index de recherche, un extrait, un courriel, un
résumé lu à voix haute) : le texte de chaque bloc sans HTML, décodé, les blocs séparés par une ligne vide, les blocs
vides omis.

| Type | Texte |
|---|---|
| `paragraph`, `header`, `alert`, `runningFooter`, `footnote`, `raw` | le texte |
| `banner`, `watermark`, `code`, `mermaid`, `math` | la valeur brute |
| `quote`, `credit`, `personality` | les parties jointes par ` — ` |
| `warning` | le titre et le message |
| `delimiter` | `***` |
| `list` | un élément par ligne, `-`, `1.` ou `[x]`/`[ ]`, deux espaces par niveau |
| `definitionList` | chaque terme, puis ses définitions en retrait |
| `table` | une rangée par ligne, cellules séparées par ` | ` |
| `index` | le titre, puis `term, pages`, deux espaces par niveau |
| `image`, `embed`, `gallery` | les légendes ; une image sans légende, son `alt` |
| `linkTool` | le titre et l'adresse |
| `attaches` | le titre, ou le nom du fichier |
| `button` | le texte et l'adresse entre parenthèses |
| `toggle`, `note`, `descriptive` | le titre, puis les blocs imbriqués |
| `wide`, `columnFlow`, `container`, `columns` | les blocs imbriqués |
| `statblock` | le nom, le sous-titre, puis chaque section : titre, `label: value`, capacités, blocs imbriqués |
| les marqueurs de page, les décorations, `chart`, `qrCode`, `toc`, et tout type inconnu | rien |

`with()` remplace la conversion d'un type, ou en ajoute une :

```php
$text = new PlainText()->with('callout', static fn (Block $block, PlainText $text): string => $text->text($block->data['text'] ?? null));
```

`passages($document, $language)` donne le texte par langue, pour un index de recherche qui lit chaque passage avec
l'analyseur de sa langue. Chaque bloc du premier niveau du document va à la langue que nomme son réglage `language`,
en minuscules, ou à la langue donnée pour le document ; les passages en ligne (`<span lang>`) restent avec leur bloc.
Les langues viennent dans l'ordre de leur premier bloc, et une langue dont les blocs ne donnent aucun texte est omise.

```php
foreach (new PlainText()->passages($document, 'fr') as $language => $text) {
    $fields['body_'.str_replace('-', '_', $language)] = $text; // body_fr, body_en_ca…
}
```

La conversion reçoit le convertisseur, dont `text()` retire le HTML et dont `blocks()` convertit les blocs imbriqués.
`Ujo::toText()` normalise d'abord le document.
