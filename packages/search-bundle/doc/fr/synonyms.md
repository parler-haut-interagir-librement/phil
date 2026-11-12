<!-- phil-dev source-sha256=89dfd37e6d9e21c88b8cacce705847d71c8b8548c49156074a9dc3dc64ef41c7 -->
# Synonymes

Une recherche lit ses mots avec les synonymes de leur langue. La langue est celle que donne la requête, sinon celle de
son filtre `locale` quand c'est une seule valeur — `fr_CA` se lit `fr` — ; une requête sans langue est cherchée telle
quelle.

## Règles

| Règle | Écrite | Une recherche de |
|---|---|---|
| Équivalence | `SynonymRule::equivalent(['jdr', 'jeu de rôle'])` | l'une ou l'autre trouve les deux |
| Sens unique | `SynonymRule::oneWay(['donjon'], ['donjon', 'dungeon'])` | `donjon` trouve `donjon` et `dungeon` ; `dungeon` ne trouve que lui-même |

Une règle à sens unique remplace les expressions de sa gauche par celles de sa droite, comme le fait le format de Solr :
inscrivez aussi l'expression de gauche à droite pour continuer à la trouver. Chaque expression est comparée en
minuscules, chaque suite d'espaces blancs devenue une seule espace ; elle fait de 1 à 100 caractères et 8 mots au plus,
et une règle contient 64 expressions au plus.

## Fournisseurs

Chaque service qui implémente `SynonymProvider` reçoit l'étiquette `phil_search.synonym_provider`, et les règles de
tous sont lues ensemble.

| Source | Configurée avec |
|---|---|
| Des fichiers du projet, un par langue au format de Solr — `config/synonyms/fr.txt` —, par `phil/search-synonyms` | `synonyms.directory` |
| Un magasin de `phil/search-synonyms` propre à l'application — une table que l'administration modifie | `synonyms.store`, son service |
| Les formes qu'un vocabulaire rejette pour ses termes préférés, chacune un service qui implémente `RejectedForms` | `synonyms.rejected_forms`, leurs services |
| Toute autre source | un service qui implémente `SynonymProvider` |

```yaml
phil_search:
    synonyms:
        directory: '%kernel.project_dir%/config/synonyms'
        rejected_forms: ['app.taxonomy.rejected_forms']
        languages: ['fr', 'en']   # for Meilisearch, see below
```

Une forme rejetée est un synonyme à sens unique d'elle-même et de son terme préféré : `D&D` trouve `D&D` et `Donjons et
Dragons`, et `Donjons et Dragons` ne trouve que lui-même. Une forme qui ne peut pas être un synonyme — trop longue,
identique à son terme — est écartée.

## Comment une requête est élargie

`SynonymExpander` lit les règles d'une langue chez chaque fournisseur à la première recherche de cette langue, puis les
garde jusqu'à la réinitialisation du conteneur, entre deux requêtes d'un *worker*. À partir de la gauche de la requête,
la plus longue expression qu'une règle connaît devient un seul terme avec ses variantes — chaque expression d'une règle
d'équivalence, le côté droit d'une règle à sens unique —, puis vient le mot suivant, jusqu'à la fin. Un terme garde 32
variantes au plus, les premières lues ; une expression à gauche de plusieurs règles reçoit les variantes de toutes.
Les règles sont appliquées une fois, jamais en chaîne.

`jeu de rôle donjon`, avec les règles ci-dessus, fait deux termes : `jeu de rôle | jdr`, et `donjon | dungeon`. L'index de
la base de données trouve un document qui contient une variante de chaque terme.

## Les moteurs qui gardent des synonymes

Meilisearch garde une liste de synonymes par index et l'applique lui-même : le bundle lui donne les synonymes des langues
de `synonyms.languages`, fusionnés, après chaque reconstruction et avec `phil:search:synonyms`, lancée une fois les
synonymes modifiés. Une recherche y envoie les mots tels quels. Meilisearch ajoute des synonymes et ne remplace jamais
un mot : une règle à sens unique trouve donc encore le mot de sa gauche ; et sa liste est la même pour chaque langue de
l'index — un mot qui a deux sens dans deux langues se cherche mieux dans un index par langue.

Loupe ne lit ni les variantes d'une requête ni une liste à lui : avec `adapter: loupe`, les synonymes ne sont pas
appliqués.

```bash
bin/console phil:search:synonyms          # every index
bin/console phil:search:synonyms games    # one
```
