<!-- phil-dev source-sha256=37d07836e41b950d09a3e559aac3664f8427243a89279997c5922d1779002495 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilDoctrineBehavioursBundle`, `BehaviourServices` | Chaque comportement activé par défaut, chacun désactivé, les étiquettes d'événement, les services et leurs arguments, `kernel.reset` sur `TreeListener`, les filtres ajoutés comme le disent les configurations, rien sans DoctrineBundle, les erreurs pour ce qui manque à un comportement |
| `SecurityBlameResolver` | Personne sans jeton ou sans le bundle de sécurité, l'identifiant de l'utilisateur avec un jeton, l'usurpateur sous `switch_user`, la méthode que nomme `blame.user_method` |
| `SecurityOwnerResolver` | Personne sans jeton, la méthode nommée, le compte incarné sous `switch_user`, une adresse courriel refusée |
| `SecurityVersionRestoreGuard` | Le droit demandé avec l'enregistrement comme sujet, le 403, personne sans Security |
| `BlamePrivacy`, `OwnerPrivacy` | Les lignes d'une personne comptées par table, l'effacement par un pseudonyme ou par personne, le rapport de chaque table, une personne concernée avec un `@` qui ne possède rien |
| Une application entière | FrameworkBundle, DoctrineBundle et `phil/slug-bundle` sur SQLite en mémoire, le bundle configuré en YAML : une entité horodatée, placée dans son arbre, supprimée en douceur et cachée par le filtre, puis l'arbre lu ; un enregistrement avec un identifiant daté et un *slug* unique, publié par `workflow.registry` comme le ferait un planificateur, figé, dupliqué, désactivé, et refusé au retour en arrière ; des étapes ordonnées dans leur liste de vérification et déplacées ; un *slug* unique dans sa locale ; une traduction écrite, relue le long d'une chaîne de repli, avec son index unique |
| Les instants | Chaque colonne d'instant que la bibliothèque associe, écrite en UTC depuis une horloge d'un autre fuseau horaire, relue sous le type que déclare la bibliothèque : les attentes suivent ce type, jamais un type écrit dans le test |
| L'application de test | `lint:container`, `lint:expressions` et `lint:yaml` par `phil-dev lint-apps` |

```sh
vendor/bin/phpunit packages/doctrine-behaviours-bundle/tests
vendor/bin/phil-dev lint-apps doctrine-behaviours-bundle
```
