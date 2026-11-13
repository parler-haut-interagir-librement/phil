<!-- phil-dev source-sha256=dbeec9adc9eb8b952a9cd18a33f7bb2200c6bebd3234093a1f7046dc698f9959 -->
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
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `store` | `doctrine` | L'endroit où les sondages et les bulletins sont conservés : les stockages Doctrine, ou ceux en mémoire, pour les tests, vidés entre deux requêtes. |
| `entity_manager` | `default` | Le gestionnaire d'entités des stockages Doctrine. |
| `policy` | `null` | Le service qui dit qui peut créer un sondage, voter et le gérer. |
| `results_cache.pool` | `null` | Le pool de cache, un `Symfony\Contracts\Cache\CacheInterface`, où les dépouillements sont gardés jusqu'au prochain bulletin ; aucun par défaut, chaque dépouillement lisant chaque bulletin. |
| `results_cache.lifetime` | `3600` | Les secondes pendant lesquelles un dépouillement est gardé, au plus, 1 ou plus. |

## Les services

| Service | Public | Nature |
|---|---|---|
| `Phil\PollBundle\Application\Polls` | oui | Le point d'entrée de l'application. |
| `Phil\PollBundle\Application\PollViews` | oui | Le formulaire d'un bulletin et les résultats, tels que les voit une personne. |
| `Phil\Poll\CountingMethods` | oui | Les méthodes de dépouillement : les quatre de la bibliothèque, le vote unique transférable et celles de l'application. |
| `Phil\PollBundle\Domain\Repository\PollRepository` | oui | Un alias du stockage Doctrine ou en mémoire des sondages. |
| `Phil\PollBundle\Domain\Repository\VoteRepository` | oui | Un alias du stockage Doctrine ou en mémoire des bulletins et des participations. |
| `Phil\PollBundle\Application\Contract\PollPolicy` | non | Un alias de la politique configurée, ou de `DefaultPollPolicy`. |
| `ChoiceCount`, `InstantRunoff`, `Borda`, `Schulze` | non | Les méthodes de la bibliothèque, étiquetées `phil_poll.counting_method`. |
| `Phil\PollBundle\Domain\Counting\MeekStv` | non | Le vote unique transférable, étiqueté `phil_poll.counting_method`. |
| `Phil\PollBundle\Infrastructure\Twig\PollTwig` | non | Les fonctions `phil_poll_ballot()` et `phil_poll_results()`, enregistrées quand Twig est installé. |

Chaque service de l'application qui implémente `Phil\Poll\Contract\CountingMethod` reçoit l'étiquette
`phil_poll.counting_method` par autoconfiguration et est ajouté à `CountingMethods`. `Polls` diffuse ses événements par
`event_dispatcher` quand l'application en a un.

## Le stockage en mémoire

Les stockages de `store: memory` portent l'étiquette `kernel.reset` : ils sont vidés entre deux requêtes, sous PHP-FPM
comme dans un *worker*, pour que rien de ce qu'écrit une requête n'atteigne la suivante. Un test qui lit ce qu'une
requête antérieure a écrit utilise le stockage Doctrine sur SQLite.

## Le cache des dépouillements

Avec `results_cache.pool`, `results()`, `resultsFor()`, `election()` et `electionFor()` gardent chaque dépouillement
dans le pool, sous une clé qui nomme le sondage et la révision de ses bulletins. Chaque bulletin déposé, modifié ou
retiré fait monter la révision dans le stockage, par une seule instruction, si bien que le dépouillement suivant lit de
nouveau les bulletins ; un dépouillement qui tournait pendant qu'un bulletin arrivait est gardé sous une révision que
plus personne ne demande, et expire avec sa durée de vie. La fermeture d'un sondage ne change le dépouillement de
personne. Le pool ne contient que les dépouillements : quelles options ont gagné, de combien, jamais qui a voté.
