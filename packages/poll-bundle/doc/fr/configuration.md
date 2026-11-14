<!-- phil-dev source-sha256=abbaad7204745e1cce7309c0c582f3adaeb19b48e757a2b894a5066ccc94b1d8 -->
# Configuration

```yaml
# config/packages/phil_poll.yaml
phil_poll:
    store: doctrine          # or "memory", for tests
    entity_manager: default  # the Doctrine entity manager of the tables
    policy: null             # a service implementing PollPolicy; DefaultPollPolicy when null
    results_cache:
        pool: null           # a cache pool, such as "cache.app"; no count is kept when null
        lifetime: 3600       # the seconds a count is kept at most
    kit_subdirectory: null   # where the recipes of the kit are; the subdirectory of "ux_toolkit" when null
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `store` | `doctrine` | L'endroit où les sondages et les bulletins sont conservés : les stockages Doctrine, ou ceux en mémoire, pour les tests, vidés entre deux requêtes. |
| `entity_manager` | `default` | Le gestionnaire d'entités des stockages Doctrine. |
| `policy` | `null` | Le service qui dit qui peut créer un sondage, voter et le gérer. |
| `results_cache.pool` | `null` | Le pool de cache, un `Symfony\Contracts\Cache\CacheInterface`, où les dépouillements sont gardés jusqu'au prochain changement du sondage ; aucun par défaut, chaque dépouillement lisant chaque bulletin. |
| `results_cache.lifetime` | `3600` | Les secondes pendant lesquelles un dépouillement est gardé, au plus, 1 ou plus. |
| `kit_subdirectory` | `null` | Le sous-répertoire du répertoire des composants qui contient les recettes du kit, `ui` pour `ui:Field` ; le sous-répertoire de la configuration `ux_toolkit` quand il vaut null, aucun sans elle. À définir dans un environnement où le Toolkit n'est pas activé. |

## Les services

| Service | Public | Nature |
|---|---|---|
| `Phil\PollBundle\Application\Polls` | oui | Le point d'entrée de l'application. |
| `Phil\PollBundle\Application\PollViews` | oui | Le formulaire d'un bulletin et les résultats, tels que les voit une personne. |
| `Phil\Poll\CountingMethods` | oui | Les méthodes de dépouillement : les six de la bibliothèque, le vote unique transférable et celles de l'application. |
| `Phil\PollBundle\Domain\Repository\PollRepository` | oui | Un alias du stockage Doctrine ou en mémoire des sondages. |
| `Phil\PollBundle\Domain\Repository\VoteRepository` | oui | Un alias du stockage Doctrine ou en mémoire des bulletins et des participations. |
| `Phil\PollBundle\Application\Contract\PollPolicy` | non | Un alias de la politique configurée, ou de `DefaultPollPolicy`. |
| `ChoiceCount`, `InstantRunoff`, `Borda`, `Schulze` | non | Les méthodes de la bibliothèque, étiquetées `phil_poll.counting_method`. |
| `Phil\PollBundle\Domain\Counting\MeekStv` | non | Le vote unique transférable, étiqueté `phil_poll.counting_method`. |
| `Phil\PollBundle\Infrastructure\Twig\PollTwig` | non | Les fonctions `phil_poll_ballot()`, `phil_poll_results()` et `phil_poll_kit()`, enregistrées quand Twig est installé. |

Chaque service de l'application qui implémente `Phil\Poll\Contract\CountingMethod` reçoit l'étiquette
`phil_poll.counting_method` par autoconfiguration et est ajouté à `CountingMethods`. `Polls` diffuse ses événements par
`event_dispatcher` quand l'application en a un.

## Le stockage en mémoire

Les stockages de `store: memory` portent l'étiquette `kernel.reset` : ils sont vidés entre deux requêtes, sous PHP-FPM
comme dans un *worker*, pour que rien de ce qu'écrit une requête n'atteigne la suivante. Un test qui lit ce qu'une
requête antérieure a écrit utilise le stockage Doctrine sur SQLite.

## Le cache des dépouillements

Avec `results_cache.pool`, `results()`, `resultsFor()`, `election()` et `electionFor()` gardent chaque dépouillement
dans le pool, sous une clé qui nomme le sondage et sa révision. Chaque bulletin déposé, modifié ou retiré, chaque fermeture et
chaque nouveau calendrier fait monter la révision dans le stockage, par une seule instruction, si bien que le
dépouillement suivant lit de nouveau les bulletins ; la révision est lue dans la table, non dans le sondage que détient
le gestionnaire d'entités. Un dépouillement qui tournait pendant qu'un bulletin arrivait est gardé sous une révision que
plus personne ne demande, et expire avec sa durée de vie. Supprimer un sondage retire du pool les dépouillements de sa
révision courante. Le pool ne contient que les dépouillements — quelles options ont gagné, de combien, la somme des
notes d'une échelle —, jamais un votant, un reçu ou son empreinte, que le sondage soit anonyme ou non.
