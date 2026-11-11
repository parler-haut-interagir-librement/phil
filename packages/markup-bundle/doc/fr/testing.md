<!-- phil-dev source-sha256=479faafb8252bb35682ff2ca172dd74c11643c6a40f81fd94984b5e7d21dbef2 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilMarkupBundle`, `MarkupServices` | Les services, leurs alias et leurs arguments ; les formats, un format nommé deux fois, un fichier ultérieur qui remplace un fichier antérieur, le format par défaut et un format non activé ; les niveaux, un niveau ou un format refusé ; les hôtes de médias et ceux qui sont refusés |
| `MarkupFormats`, `UnavailableFormat` | Les formats activés, le format par défaut, un format inconnu ou non activé, les niveaux abaissés à chaque plafond, le format d'un complément et son libellé |
| `UjoConverter` | Chaque format activé vers un document et son JSON, les métadonnées, le JSON indenté, le HTML brut jusqu'au plafond, un format non activé, une source UJO invalide |
| `MarkupFormatType` | Les formats activés offerts dans l'ordre avec leurs libellés, un format soumis, un format non activé, un libellé pour chaque format, le format d'un complément avec son libellé |
| `MarkupExtension` | Les trois filtres, leurs valeurs par défaut, les plafonds, `null`, un format non activé |
| `MediaHostsPass` | Les hôtes donnés à un adaptateur qu'a enregistré un autre bundle, un purificateur construit autrement laissé intact |
| Une application complète | FrameworkBundle avec les formulaires et un traducteur français, et TwigBundle : un gabarit qui compile du BBCode à deux niveaux, purifie un texte stocké et écrit de l'UJO ; le convertisseur ; le type de formulaire rendu avec ses libellés français |

```sh
vendor/bin/phpunit packages/markup-bundle/tests
```

## Bancs d'essai

`MarkupExtensionBench` exécute les trois filtres Twig sur du Markdown et du BBCode, d'un commentaire à un long message.
Depuis la racine du monodépôt :

```sh
composer bench -- packages/markup-bundle/tests/Benchmark
```
