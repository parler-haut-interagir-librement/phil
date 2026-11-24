<!-- phil-dev source-sha256=209eb9c1970116ee5572e1e90649756720bbea04df57d1cfcfe01659fa1f429b -->
# Configuration

La configuration du bundle se trouve sous `phil_survey` ; chaque option a une valeur par défaut :

```yaml
# config/packages/phil_survey.yaml
phil_survey:
    connection: default
    secret: '%kernel.secret%'
    redirect_origins: ['https://example.org']
    allow_localhost: false
    respondent: null
    sessions: null
    pages: true
    schedule:
        enabled: true
        at: '03:45'
        timezone: UTC
        lock_factory: lock.factory
        state_pool: cache.app
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `connection` | `default` | La connexion Doctrine des tables. |
| `secret` | `%kernel.secret%` | La clé des empreintes qui marquent les participations, 16 octets ou plus. |
| `redirect_origins` | aucune | Les origines vers lesquelles une fin peut rediriger ; sans origine, aucune fin ne redirige. |
| `allow_localhost` | `false` | Si `localhost` est un hôte de redirection, pour le développement et les tests. |
| `respondent` | `null` | Le service qui donne le membre connecté, un `CurrentSubject` de `phil/forms-bundle` ; le membre de Symfony Security si `null`, personne sans lui. |
| `sessions` | `null` | Le service `FeedbackSessions` qui donne le début et la fin de chaque séance ; aucune rétroaction de séance n'est ouverte si `null`. |
| `pages` | `true` | Si le bundle enregistre ses pages, avec Twig. |
| `schedule.enabled` | `true` | Si le calendrier `phil_survey` purge chaque jour les réponses échues, avec Symfony Scheduler. |
| `schedule.at`, `schedule.timezone` | `03:45`, `UTC` | L'heure de la purge. |
| `schedule.lock_factory` | `lock.factory` | La fabrique de verrous qui limite le calendrier à un seul *worker*, si elle existe. |
| `schedule.state_pool` | `cache.app` | Le groupe de cache qui garde la dernière exécution du calendrier, s'il existe. |

Les pages exigent la protection CSRF de Symfony, `framework.csrf_protection: true` ; son jeton est sans état et n'a pas
besoin de session. Les [pages](pages.md) disent comment importer les routes.

`phil_survey.respondent` et `phil_survey.sessions` nomment des services de l'application : voyez les
[points d'extension](extension-points.md).

Les pages exigent la protection CSRF de Symfony, `framework.csrf_protection: true` ; son jeton est sans état et n'a pas
besoin de session. Les [pages](pages.md) disent comment importer les routes.

`phil_survey.respondent` et `phil_survey.sessions` nomment des services de l'application : voyez les
[points d'extension](extension-points.md).
