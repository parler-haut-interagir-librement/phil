<!-- phil-dev source-sha256=9c8c62fc2e58b1d5fe3e007e311e160f9cdb3aba0d3f1a328e0d497c395fda24 -->
# Magasins

`SynonymStore` garde la liste de chaque langue :

| Méthode | Contrat |
|---|---|
| `get(string $language): SynonymList` | La liste d'une langue, lue sous sa sous-étiquette primaire ; une liste vide pour une langue sans liste |
| `save(SynonymList $list): void` | Remplace la liste de sa langue |
| `languages(): list<string>` | Les langues qui ont une liste, par ordre alphabétique |

## Fichiers

`FileSynonymStore` garde un fichier par langue dans un répertoire, au [format de Solr](formats.md) : `fr.txt`,
`en.txt` — des fichiers qu'un projet garde sous gestion de versions, ou qu'une administration écrit. Chaque fichier est
lu à la demande, de sorte qu'un *worker* de longue durée voit le dernier enregistré ; un enregistrement écrit un fichier
à côté, puis le déplace en place, de sorte qu'un lecteur ne voit jamais la moitié d'un fichier. Le répertoire est créé
au premier enregistrement d'une liste. Un fichier qui contient une ligne qui n'est pas une règle est refusé à sa
lecture.

## Mémoire

`InMemorySynonymStore` garde les listes qu'on lui donne, pour les tests et pour les listes que l'application construit
elle-même.

## Un magasin de l'application

Une table que l'administration modifie est une classe qui implémente `SynonymStore`, donnée au conteneur comme
n'importe quel service.

## Avec phil/search-bundle

`phil/search-bundle` lit un magasin par son port de synonymes :

```yaml
phil_search:
    synonyms:
        directory: '%kernel.project_dir%/config/synonyms'   # a FileSynonymStore
        # or
        store: App\Search\TableSynonymStore                  # a store of the application's
```

Une règle d'équivalence reste une équivalence et une règle à sens unique reste à sens unique ; le bundle lit les règles
d'une langue à la première recherche de cette langue dans une requête.
