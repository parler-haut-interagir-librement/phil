<!-- phil-dev source-sha256=6bf487671b2db8e5b90f9e9c22958e0f8b3baafba42a5e229ce4655a1b4b2092 -->
# Points d'extension

Ce qu'une application implémente, remplace, configure ou utilise.

## Ports

### `DocumentProvider`

`Phil\SearchBundle\Application\DocumentProvider` — change une sorte de ressource en documents d'un index. Un service
qui l'implémente reçoit l'étiquette `phil_search.provider` par autoconfiguration. Voir [Fournisseurs](providers.md).

| Méthode | Contrat |
|---|---|
| `index(): string` | L'index où vont ses documents |
| `supports(object $resource): bool` | Indique s'il lit cette ressource |
| `document(object $resource): ?Document` | Le document de la ressource, ou `null` pour la garder hors de l'index — un brouillon ne doit pas être trouvé |
| `id(object $resource): string` | L'identifiant de la ressource dans l'index, lu avant qu'une suppression le vide |
| `all(): iterable<object>` | Chaque ressource de l'index, pour une reconstruction ; parcourues, jamais chargées d'un coup |

### `SearchIndex`

`Phil\SearchBundle\Application\SearchIndex` — l'endroit où les documents sont indexés et cherchés. `DbalSearchIndex`,
`MeilisearchIndex`, ou `LoupeIndex` de `phil/search-loupe`, selon `adapter` ; une application crée un alias du port
vers son propre moteur.

| Méthode | Contrat |
|---|---|
| `index(Document ...$documents): void` | Ajoute les documents, ou remplace ceux qui ont les mêmes identifiants, d'un seul coup |
| `remove(string $index, list<string> $ids): void` | Retire ces documents |
| `clear(string $index): void` | Vide l'index |
| `rebuild(string $index, iterable<list<Document>> $batches): void` | Remplace chaque document, l'index répondant aux recherches avec ce qu'il contenait jusqu'à l'écriture du dernier lot ; une reconstruction qui échoue le laisse tel qu'il était |
| `search(string $index, SearchQuery $query): SearchResult` | Une page de documents trouvés, et le total |

### `SynonymProvider`

`Phil\SearchBundle\Application\Contract\SynonymProvider` — donne les synonymes d'une langue. Un service qui
l'implémente reçoit l'étiquette `phil_search.synonym_provider` par autoconfiguration. Voir [Synonymes](synonyms.md).

| Méthode | Contrat |
|---|---|
| `rules(string $language): iterable<SynonymRule>` | Les règles d'une langue, une sous-étiquette primaire en minuscules : `fr` ; aucune pour une langue qu'il ne connaît pas |

### `RejectedForms`

`Phil\SearchBundle\Application\Contract\RejectedForms` — les formes qu'un vocabulaire rejette pour ses termes
préférés, lues comme des synonymes à sens unique par `RejectedFormSynonyms` ; ses services sont listés sous
`synonyms.rejected_forms`.

| Méthode | Contrat |
|---|---|
| `rejectedForms(string $language): iterable<array{string, string}>` | Chaque forme rejetée et son terme préféré |

### `SynonymAwareIndex`

`Phil\SearchBundle\Application\Contract\SynonymAwareIndex` — implémentée par un index qui applique lui-même les
synonymes. `Search` lui envoie alors les mots tels quels, et lui donne les synonymes de `synonyms.languages` après
chaque reconstruction et avec `phil:search:synonyms`.

| Méthode | Contrat |
|---|---|
| `replaceSynonyms(string $index, array<string, list<string>> $synonyms): void` | Remplace les synonymes d'un index : chaque expression et les autres qu'elle trouve aussi |

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `adapter` | `dbal`, `meilisearch` ou `loupe` |
| `connection`, `meilisearch`, `loupe.directory` | L'endroit où chaque adaptateur garde les index |
| `index_entities` | Indique si chaque entité que modifie un `flush` de Doctrine est indexée |
| `batch` | Le nombre de documents qu'une reconstruction écrit à la fois |
| `synonyms.directory`, `synonyms.store`, `synonyms.rejected_forms` | L'origine des synonymes |
| `synonyms.languages` | Les langues que reçoit un moteur qui garde des synonymes |
| `kit_subdirectory` | L'emplacement des recettes du kit |

## Gabarits et contrôleur

Le gabarit `@PhilSearch/components/SearchFilters.html.twig` se remplace comme n'importe quel gabarit de bundle, dans
`templates/bundles/PhilSearchBundle/components/`. Le composant lit ses recettes par leur nom : un kit qui a les mêmes
noms et les mêmes propriétés — le kit Tailwind `phil/ux-kit-shadcn` — le dessine donc aussi. Le contrôleur ne répartit
aucun événement et ne lit que ses cibles, `form`, `status` et `submit`, et sa valeur `results`.

## Services

`Search` — `index()`, `remove()`, `forget()`, `reindex()`, `search()`, `publishSynonyms()` — est ce qu'appelle
l'application pour les ressources qui ne sont pas des entités Doctrine, et pour chercher. Voir
[Recherche](searching.md). `SearchRequest` lit la chaîne de requête d'une page de résultats. Voir
[Filtres](filters.md).
