<!-- phil-dev source-sha256=f805c338c947f3c9f59794ad1aead818e8ab5dd6d2eb9ff0881b03acacc99040 -->
# Points d'extension

Le paquet est un seul adaptateur : `LoupeIndex`, le `Phil\SearchBundle\Application\SearchIndex` de
`phil/search-bundle` sur Loupe. Il ne déclare aucun port à lui ; ce qu'une application choisit passe par le bundle et
par le constructeur.

| Point | Comment |
|---|---|
| Le choisir | `adapter: loupe` dans la configuration de `phil/search-bundle` |
| Où vivent les index | `loupe.directory`, un répertoire par index ; sans lui, ils restent en mémoire, pour les tests |
| Comment Loupe est construit | La `LoupeFactoryInterface` donnée à `LoupeIndex` : la fabrique propre à l'application, pour configurer Loupe autrement |
| Ce qui est cherché et filtré | Les documents que donnent les `DocumentProvider` du bundle : les champs texte sont cherchés, dans l'ordre où un fournisseur les donne, et les filtres sont gardés comme listes de texte — voyez [Comment il indexe](indexing.md) |

Une application qui veut un autre moteur écrit son propre `SearchIndex` pour `phil/search-bundle` plutôt que
d'étendre celui-ci : `LoupeIndex` est final.
