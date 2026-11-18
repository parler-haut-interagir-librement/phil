<!-- phil-dev source-sha256=c0951b54989341ca13708dcb0a65a2f100f099a0605edf99b3c8f2e4bb300e69 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilMarkupBundle`, `MarkupServices` | Les services, leurs alias et leurs arguments ; les formats, un format nommé deux fois, un fichier ultérieur qui remplace un fichier antérieur, le format par défaut et un format non activé ; les niveaux, un niveau ou un format refusé ; les hôtes de médias et ceux qui sont refusés ; le purificateur choisi, et `ammonia` refusé sans son extension ; les trois plafonds, leurs valeurs par défaut, les services et les paramètres qu'ils atteignent, une valeur qui n'est pas un entier positif refusée |
| `MarkupFormats`, `UnavailableFormat` | Les formats activés, le format par défaut, un format inconnu ou non activé, les niveaux abaissés à chaque plafond, le format d'un complément et son libellé |
| `UjoConverter` | Chaque format activé vers un document et son JSON, les métadonnées, le JSON indenté, le HTML brut jusqu'au plafond, un format non activé, une source UJO invalide |
| `MarkupFormatType` | Les formats activés offerts dans l'ordre avec leurs libellés, un format soumis, un format non activé, un libellé pour chaque format, le format d'un complément avec son libellé |
| `MarkupExtension` | Les trois filtres, leurs valeurs par défaut, les plafonds, `null`, un format non activé |
| `MediaHostsPass` | Les hôtes, et le plafond d'un HTML purifié, donnés à un adaptateur qu'a enregistré un autre bundle et à chaque fabrique des politiques par défaut, un purificateur construit autrement laissé intact, `HtmlPurifier` rendu au purificateur choisi |
| `MaxBytesPass` | Les plafonds donnés à un compilateur qu'a enregistré un autre bundle, ses autres arguments gardés, le compilateur construit refusant une source plus longue dans un format écrit et en UJO ; un compilateur à qui l'on donne ses plafonds par position, construit par une fabrique ou d'une autre classe laissé intact ; rien sans les deux paramètres |
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
