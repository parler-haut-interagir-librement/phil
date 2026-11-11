<!-- phil-dev source-sha256=4331c954218748d99f427f5523b1d5291dba189142487e31c8e478146853e900 -->
# Conversion en UJO

`Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter`, un service public, convertit une source dans tout format
activé en UJO (Universal JSON Output), le format de document par blocs de `phil/ujo` : ce qu'ouvre un éditeur par blocs,
et ce que garde un texte une fois publié.

| Méthode | Donne |
|---|---|
| `reader($format = null, $level = null)` | Le `MarkupReader` d'un format, à un niveau |
| `read($source, $format = null, $level = null)` | Le `Reading` : le `Document` UJO et les métadonnées écrites à part du texte |
| `toJson($source, $format = null, $level = null, $pretty = false)` | Le JSON UJO du document |

Un format et un niveau se donnent sous forme des enums de `phil/markup` ou de leurs valeurs, un format de complément par
son nom ; `null` prend le `default_format` et le `default_level` de la [configuration](configuration.md). Un format non
activé lance `UnavailableFormat`, une source UJO invalide lance `InvalidSource` ; les analyseurs des formats qu'écrivent
les gens ne lancent jamais d'exception.

```php
$reading = $converter->read("---\ntitle: Notes\n---\n# Hello", 'markdown');
$reading->metadata;                         // ['title' => 'Notes']

$json = $converter->toJson('[b]Bonjour[/b]', 'bbcode');
// '{"blocks":[{"id":"b1","type":"paragraph","data":{"text":"<b>Bonjour</b>"}}]}'
```

## Tous les formats

Le Markdown, le BBCode, MediaWiki, DokuWiki, Creole et le HTML sont lus par les analyseurs de `phil/markup`, et les
formats des compléments par les analyseurs qu'ils déclarent, chaque fonctionnalité étant associée au bloc UJO de même
rôle. Le texte brut devient un paragraphe par suite de lignes. UJO est lu et vérifié par `phil/ujo`, puis réécrit sous
forme canonique. La correspondance de chaque fonctionnalité dans chaque format se trouve dans la documentation des
formats de `phil/markup`.

## Le niveau

Le niveau auquel une source est lue décide d'une seule chose : le HTML brut qui y est écrit (un `<div>` en Markdown,
`[html]` en BBCode, `<html>` dans un wiki) devient un bloc `raw` à `all`, et du texte échappé en dessous. Le niveau
demandé est abaissé au plafond du format, comme pour les filtres : avec `levels: { bbcode: forum }`, le HTML brut d'une
source BBCode est du texte, quoi que demande l'appel. Le document n'est pas purifié : ses blocs gardent leurs titres et
leurs tableaux, et le purificateur s'applique quand le document est rendu et affiché.
