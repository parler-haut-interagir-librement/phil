<!-- phil-dev source-sha256=37d3ec8e606479fbd6d71c0a739db739e70d64defc3591e698009cdd84c0538b -->
# Recherche

Avec `phil/search-bundle` dans le noyau, les contenus publiés alimentent l'un de ses index, `content` par défaut
(`phil_content.search_index`), par `Infrastructure\Search\ContentDocumentProvider`. L'index suit ce que les lecteurs
voient : chaque vidage de Doctrine qui publie une locale ajoute ou remplace son document, et la dépublication ou la
suppression le retire.

## Les documents

Un document par ligne localisée en ligne des racines déclarées :

| Partie | Contient |
|---|---|
| Identifiant | le genre, l'identifiant de la racine et la locale : `news-0192f3c4-…-fr_CA` |
| `title` | le titre de l'extrait, sinon le titre de référencement |
| `body` | le passage de l'UJO (Universal JSON Output) publié dans la langue propre de la ligne |
| `body_<language>` | chaque passage qu'une autre langue accorde, comme `body_en` pour une citation anglaise dans une page française, de sorte que l'index lit chacun dans sa propre langue |
| Filtre `kind` | la clé de ressource |
| Filtre `locale` | la locale de la ligne, comme `fr_CA` |
| Filtre `reference` | la référence du contenu, pour mener au contenu |

- **Les passages** sont ceux de `PlainText::passages()` de `phil/ujo` : chaque bloc au sommet du document va à la langue
  de son accord `language`, en minuscules, ou à la langue de la ligne. Les passages et les champs vides sont omis.
- **Une ligne marquée `noindex` n'a pas de document** : elle n'est pas trouvée.
- **La reconstruction** (`phil:search:reindex content`) lit chaque racine de chaque genre, dans chaque locale déclarée,
  et indexe sa ligne en ligne.

## Chercher

```php
use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;

$result = $search->search('content', SearchQuery::of('opening', ['locale' => 'fr', 'kind' => 'news']));
// $result->ids: the identifiers of the documents found
```

L'application transforme un résultat en lien avec le filtre `reference` du document, ou en relisant l'identifiant, et
`content_url()` ([rendering.md](rendering.md#les-fonctions-twig)).
