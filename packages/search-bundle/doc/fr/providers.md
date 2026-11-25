<!-- phil-dev source-sha256=f20c2b1abbc2e9d8b8137dbb5aef4e8bc701db77c14f5e3665862b349fff9055 -->
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

`index_entities` dit comment les index apprennent un `flush` :

| Mode | Valeur par défaut avec | Ce qui se passe |
|---|---|---|
| `outbox` | `meilisearch`, `loupe` | Les changements du `flush` sont notés dans la table de `phil/outbox-bundle`, dans la transaction du `flush`, et indexés une fois que le relais les livre |
| `direct` | `dbal` | `IndexOnFlushListener` indexe les changements du `flush` une fois qu'il réussit, dans la même requête |
| `none` | — | Rien n'est indexé à un `flush` ; l'application appelle `Search` elle-même |

`true` est la valeur par défaut de l'adaptateur, `false` vaut `none`.

### Par l'outbox

Un index hors de la base de données ne peut pas se joindre à la transaction de la base de données. Écrit après le
commit, comme le fait `direct`, il manque ce qu'un `flush` a validé pendant que le moteur était arrêté, et un `flush`
dans une transaction que l'appelant annule ensuite l'a déjà atteint. `OutboxIndexingListener` note plutôt, dans la
transaction du `flush`, sur la connexion des entités :

- chaque entité qu'un fournisseur connaît et que le `flush` insère ou met à jour, par sa classe et son identifiant ;
- chaque document d'une entité que le `flush` supprime, par son index et son identifiant dans celui-ci, lu avant que la
  ligne soit supprimée.

Les enregistrements sont validés avec les lignes, ou pas du tout. Le relais de `phil/outbox-bundle` —
`phil:outbox:relay`, son horaire ou le pont de Messenger — les reconstruit par `SearchOutboxCodec` et les envoie ;
`OutboxIndexer` relit l'entité et l'indexe telle qu'elle est alors, ne fait rien si elle a disparu, ou retire le
document. Un moteur arrêté fait échouer la livraison, que le relais réessaie après son délai, le contenu étant
sauvegardé entre-temps. Les enregistrements d'une même entité partagent une clé d'ordre : son indexation et son retrait
vont donc dans l'ordre où ils ont eu lieu, un par lot du relais.

```yaml
# config/packages/phil_outbox.yaml
phil_outbox:
    connection: default      # the connection of the entities phil_search indexes
```

- **L'outbox sur la même connexion.** `phil_outbox.connection` est la connexion de `phil_search.connection` : les
  enregistrements ne se joignent à la transaction du `flush` que là.
- **Des identifiants seulement.** Un enregistrement porte la classe et l'identifiant de l'entité, jamais un champ de son
  document. Un identifiant est une chaîne ou un entier, une énumération ou une valeur *stringable*, sans `@` ni espace ;
  un autre refuse le `flush`, et cette entité est indexée avec `index_entities: direct`.
- **Un document par livraison.** Chaque enregistrement va à l'index seul : un gros import s'indexe plus vite avec
  `phil:search:reindex` ensuite.
- Sans `phil/outbox-bundle`, `outbox` — la valeur par défaut avec Meilisearch et Loupe — est refusé à la construction
  du conteneur ; installez-le, ou choisissez `direct`.

### Après le `flush`

Avec `index_entities: direct`, `IndexOnFlushListener` indexe chaque entité qu'un `flush` insère ou met à jour, et
retire chaque entité qu'il supprime, une fois le `flush` réussi. La place d'une entité supprimée dans les index,
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
