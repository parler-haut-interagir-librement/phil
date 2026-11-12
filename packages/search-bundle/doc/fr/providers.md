<!-- phil-dev source-sha256=ec98571c0f41437036ac64303013225c0cb34777f103a39dcfea08d8f316ab43 -->
# Fournisseurs

Un `DocumentProvider` change une sorte de ressource en documents d'un index ; avec l'autoconfiguration, le bundle le
trouve.

```php
final readonly class PageDocuments implements DocumentProvider
{
    public function index(): string { return 'pages'; }

    public function supports(object $resource): bool { return $resource instanceof Page; }

    public function document(object $resource): ?Document
    {
        return $resource->isPublished()
            ? Document::of('pages', $resource->id, ['title' => $resource->title, 'body' => $resource->body], ['locale' => $resource->locale, 'tags' => $resource->tags])
            : null;   // a draft must not be found
    }

    public function id(object $resource): string { return $resource->id; }

    public function all(): iterable { return $this->pages->findAll(); }
}
```

Un document a un index, un identifiant — lettres, chiffres, `_`, `-` —, des champs de texte cherchés et des champs de
filtre, chacun un scalaire ou une liste de scalaires. Un document `null` retire la ressource de l'index.

## Ce que fait un `flush`

Avec `index_entities`, `IndexOnFlushListener` indexe chaque entité qu'un `flush` insère ou met à jour, et retire
chaque entité qu'il supprime, une fois le `flush` réussi. La place d'une entité supprimée dans les index,
`Search::addressesOf()`, est lue avant le `flush`, pendant que son identifiant est connu : Doctrine vide un
identifiant généré une fois la ligne supprimée. Un `flush` qui échoue ferme son gestionnaire d'entités, et ce qu'il
aurait indexé disparaît avec lui ; vider le gestionnaire d'entités, ou le `kernel.reset` du conteneur entre les
messages d'un *worker*, le fait aussi disparaître.

Les entités d'un `flush` vont ensemble à l'index : leurs documents en un seul appel de `SearchIndex::index()` — une
seule transaction de la table de la base de données, une seule requête à Meilisearch par index —, et leurs retraits en
un appel par index. L'ordre des changements est respecté : les entités notées après une suppression sont indexées
après elle.

Pour tout le reste, appelez `Search::index(...$resources)`, qui envoie d'un coup les documents de chaque ressource
donnée, `Search::remove()`, `Search::removeAt($addresses)` ou `Search::forget($index, ...$ids)`.

## Reconstruire

`Search::reindex()` et `phil:search:reindex` reconstruisent un index à partir de ses fournisseurs, `batch` documents à
la fois. L'index répond aux recherches tout du long, avec les documents qu'il contenait, jusqu'à la fin de la
reconstruction, et une reconstruction qui échoue le laisse tel qu'il était :

| Adaptateur | Comment |
|---|---|
| `dbal` | Une seule transaction : les autres connexions lisent les documents que contenait la table jusqu'à la validation |
| `meilisearch` | Les documents vont dans `{index}__rebuild`, qui reprend les réglages de l'index en service ; les deux sont échangés, puis l'ancien est supprimé |
| `loupe` | Les documents vont dans un nouveau répertoire à côté de celui en service, que nomme ensuite `phil_search.json` |

Une ressource modifiée pendant une reconstruction est indexée dans l'index en service, et la reconstruction peut
apporter la version qu'elle a lue : relancez la reconstruction, ou laissez le prochain `flush` de cette ressource la
corriger. Un fournisseur qui donne un document d'un autre index fait échouer la reconstruction.
