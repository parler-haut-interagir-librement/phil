<!-- phil-dev source-sha256=7e23810aef3d48033cab15ed5813570518d43942810c1f8963b130e9b3f038d3 -->
# Configuration

La configuration du bundle se trouve sous `phil_scheduling_poll`. Seuls les liens sont obligatoires :

```yaml
# config/packages/phil_scheduling_poll.yaml
phil_scheduling_poll:
    store: doctrine
    entity_manager: default
    connection: default
    links:
        keys: ['%env(SCHEDULING_POLL_LINK_KEY)%']
        page: 'https://example.org/scheduling-poll/answer'
        parameter: token
    link_lifetime: P30D
    max_respondents: 500
    closing:
        frequency: '5 minutes'
        batch: 100
    erasure:
        frequency: '1 hour'
    schedule:
        cache: cache.app
        lock_factory: lock.factory
    mailer:
        from: 'Polls <polls@example.org>'
    respondent_addresses: ~
    privacy:
        account_kind: user
    kit_subdirectory: ~
```
| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `store` | `doctrine` | Où sont conservés les sondages et les répondants : `doctrine`, ou `memory` pour les tests, vidé entre deux requêtes. |
| `entity_manager` | `default` | Le gestionnaire d'entités Doctrine des tables des sondages. |
| `connection` | `default` | La connexion DBAL de Doctrine des tables des liens. |
| `links.keys` | — | Les clés de l'empreinte à clé des liens, de 32 octets ou plus chacune, jusqu'à huit : la première calcule l'empreinte, chaque clé retrouve. |
| `links.page` | — | L'adresse absolue de la page de réponse, sans fragment ; une requête est conservée. |
| `links.parameter` | `token` | Le paramètre de requête qui porte le jeton. |
| `link_lifetime` | `P30D` | Une durée ISO 8601 : combien de temps un lien s'ouvre encore après l'échéance, et combien de temps après la clôture les noms et les adresses sont effacés. |
| `max_respondents` | `500` | Le plus grand nombre de répondants d'un sondage, de 1 à 10 000. |
| `closing.frequency` | `5 minutes` | À quelle fréquence le planificateur clôt les sondages dont l'échéance est passée. |
| `closing.batch` | `100` | Le plus grand nombre de sondages clôturés à chaque fois, de 1 à 1000. |
| `erasure.frequency` | `1 hour` | À quelle fréquence le planificateur efface les noms et les adresses dont le délai est passé. |
| `schedule.cache` | `cache.app` | Le groupe de cache où le planificateur garde son état. |
| `schedule.lock_factory` | `lock.factory` | La fabrique de verrous du verrou du planificateur ; sans elle, le planificateur n'en tient aucun. |
| `mailer.from` | `null` | L'expéditeur du courriel, `Name <address>` ; les en-têtes propres de Mailer quand c'est `null`. |
| `respondent_addresses` | `null` | Le service, un `RespondentAddresses`, qui trouve l'adresse d'un répondant qui s'est joint avec un compte. |
| `privacy.account_kind` | `user` | Le genre des références de compte dont sont les personnes concernées de `phil/privacy-bundle`. |
| `kit_subdirectory` | `null` | Où se trouvent les recettes du kit, sous le répertoire des composants ; le sous-répertoire du Toolkit quand c'est `null`. |

Une page avec un fragment, une page sur `localhost`, une durée de vie qui n'est pas une durée ISO 8601 dans sa forme à
désignateurs (`P30D`, `P1W`, `PT12H`), ou l'absence de clé, interrompt la construction.
