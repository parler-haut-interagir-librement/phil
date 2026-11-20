<!-- phil-dev source-sha256=331445a530f3d5fa0288d7972602ac071bf07f3d3a5da4d9b23b480c5ac44f49 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilDoctrineBehavioursBundle`, `BehaviourServices` | Chaque comportement activé par défaut, chacun désactivé, les étiquettes d'événement, la priorité de la suppression douce, les services et leurs arguments, les outils d'effacement sur le registre `doctrine`, `kernel.reset` sur `TreeListener` et `BlameSuspension`, la politique des champs personnels et son chiffrement, les filtres ajoutés comme le disent les configurations, rien sans DoctrineBundle, les erreurs pour ce qui manque à un comportement |
| `SuspendBlameDuringErasurePass`, `SuspendBlame`, `SuspendBlameWithExport` | Chaque outil d'effacement décoré, un outil qui exporte resté un exportateur, une classe donnée par un paramètre, rien de décoré avec la suspension désactivée ; l'effacement exécuté avec l'auteur suspendu, le module et l'export transmis |
| `SecurityBlameResolver` | Personne sans jeton ou sans le bundle de sécurité, l'identifiant de l'utilisateur avec un jeton, l'usurpateur sous `switch_user`, la méthode que nomme `blame.user_method` |
| `SecurityOwnerResolver` | Personne sans jeton, la méthode nommée, le compte incarné sous `switch_user`, une adresse courriel refusée |
| `SecurityVersionRestoreGuard` | Le droit demandé avec l'enregistrement comme sujet, le 403, personne sans Security |
| `BlamePrivacy`, `OwnerPrivacy`, `VersionsPrivacy`, `PersonalDataFields` | Les lignes d'une personne comptées par table, l'effacement par un pseudonyme ou par personne, le rapport de chaque table, une personne concernée avec un `@` qui ne possède rien ; un champ `#[PersonalData]` laissé de côté dans une version, les versions d'un propriétaire et d'une personne nommée comme auteur caviardées avant que le nom soit remplacé, relues dans la ligne brute de `phil_record_version` après l'effacement |
| `StripVersionsCommand` | Les versions déjà écrites comptées par un essai à blanc, puis débarrassées de leurs champs personnels, aucune marquée caviardée |
| L'index de recherche | `phil/search-bundle` à côté de la suppression douce : une page supprimée retirée de l'index alors que sa ligne reste, l'index qui écoute avant la suppression douce, une page purgée disparue des deux |
| Une application entière | FrameworkBundle, DoctrineBundle et `phil/slug-bundle` sur SQLite en mémoire, le bundle configuré en YAML : une entité horodatée, placée dans son arbre, supprimée en douceur et cachée par le filtre, puis l'arbre lu ; un enregistrement avec un identifiant daté et un *slug* unique, publié par `workflow.registry` comme le ferait un planificateur, figé, dupliqué, désactivé, et refusé au retour en arrière ; des étapes ordonnées dans leur liste de vérification et déplacées ; un *slug* unique dans sa locale ; une traduction écrite, relue le long d'une chaîne de repli, avec son index unique |
| Les instants | Chaque colonne d'instant que la bibliothèque associe, écrite en UTC depuis une horloge d'un autre fuseau horaire, relue sous le type que déclare la bibliothèque : les attentes suivent ce type, jamais un type écrit dans le test |
| Sur de vrais serveurs | `tests/Integration` sur MySQL 8.4 et PostgreSQL 17, à partir de `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`, sautée sans elles : quatre slugs d'une même locale qui ne diffèrent que par la casse ou l'accent sont chacun trouvés par leur seule graphie ; sur MySQL les clés sont en `utf8mb4_bin`, et les tables de la version précédente y arrivent par le diff du schéma |
| L'application de test | `lint:container`, `lint:expressions` et `lint:yaml` par `phil-dev lint-apps` |

```sh
vendor/bin/phpunit packages/doctrine-behaviours-bundle/tests
vendor/bin/phil-dev lint-apps doctrine-behaviours-bundle
```
