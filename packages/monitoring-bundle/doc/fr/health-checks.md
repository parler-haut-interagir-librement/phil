<!-- phil-dev source-sha256=9f88620837282e2f95dfe8c613d803b382fcfdbf2501bd053771e5c98f9045da -->
# Vérifications de santé

## Les vérifications du bundle

| Vérification | En service | Dégradé | Hors service |
|---|---|---|---|
| `DatabaseCheck` | La connexion répond au `SELECT 1` de la plateforme. | | La connexion ou la requête lance une exception. |
| `CachePoolCheck` | Une sonde est enregistrée, relue puis supprimée. | | Le pool refuse d'enregistrer, perd la valeur ou lance une exception. |
| `MessengerTransportCheck` | Le transport contient au plus son seuil de messages, ou ne peut pas les compter. | Il en contient plus que son seuil. | Le comptage lance une exception. |
| `FilesystemCheck` | Le répertoire existe et est accessible en écriture, avec son espace libre quand un seuil est fixé. | Son disque a moins d'espace libre que le seuil. | Le répertoire est absent ou n'est pas accessible en écriture. |
| `FlysystemCheck` | Un fichier sonde qui lui est propre, `.phil-monitoring-probe-` suivi de 16 chiffres hexadécimaux aléatoires, est écrit, relu puis supprimé à la racine du stockage, supprimé même s'il ne peut être relu : des vérifications simultanées ne se gênent jamais. | | Le stockage perd le fichier ou lance une exception. |
| `HeartbeatsCheck` | Aucune tâche déclarée n'est en retard. | Une tâche est en retard ; le résumé la nomme. | |

Une vérification qui lance une exception compte comme hors service. Son exception est journalisée au niveau erreur,
avec le nom de la vérification, et le résumé ne nomme que la classe de l'exception : son message pourrait contenir un
hôte, un chemin ou un identifiant de connexion. Une vérification qui ne perd aucune donnée mais ralentit le site est
dégradée, pas hors service. La disponibilité ne répond `503` que si une vérification est hors service, car un moniteur
retire alors le site du service.

Les vérifications s'exécutent l'une après l'autre, dans l'ordre de leur enregistrement — celles du bundle, dans l'ordre
de la configuration, puis celles de l'application —, à chaque sonde de disponibilité et à chaque appel de la vue
détaillée. Chacune est chronométrée sur l'horloge de l'application. Une vérification ne peut pas être interrompue une
fois lancée : gardez chacune rapide, et donnez aux clients qu'elle utilise leurs propres délais d'expiration, comme le
délai de connexion d'une base de données ou d'un client Redis.

## Les points de terminaison des moniteurs

| Route | Chemin sous le préfixe | Réponse |
|---|---|---|
| `phil_monitoring_live` | `GET /live` | `200 {"status":"up"}` tant que l'application répond ; aucune vérification ne s'exécute. |
| `phil_monitoring_ready` | `GET /ready` | `{"status":"up"}`, `"degraded"` ou `"down"` ; `503` si hors service, `200` sinon. |
| `phil_monitoring_details` | `GET /details` | Chaque vérification, chaque tâche et la maintenance ; pour le porteur du jeton seulement. |

La vivacité indique à un orchestrateur s'il doit redémarrer le processus. La disponibilité indique à un répartiteur de
charge ou à un moniteur de disponibilité s'il doit envoyer du trafic. Chaque réponse est en JSON, avec
`Cache-Control: no-store` et `X-Robots-Tag: noindex`, pour qu'aucun mandataire ne serve un état périmé.

## La vue détaillée

```sh
curl -H 'Authorization: Bearer <token>' https://example.com/_health/details
```

```json
{
    "status": "degraded",
    "checked_at": "2026-11-08T10:00:00+00:00",
    "checks": [
        {"name": "database.default", "status": "up", "summary": "Answers.", "duration_ms": 2},
        {"name": "messenger.failed", "status": "degraded", "summary": "3 messages waiting, over 0.", "duration_ms": 1}
    ],
    "heartbeats": [
        {"task": "backup", "state": "on_time", "last_beat_at": "2026-11-08T03:00:00+00:00", "due_by": "2026-11-09T04:00:00+00:00"}
    ],
    "maintenance": null
}
```

Le jeton est `health.token`, comparé en temps constant à l'en-tête `Authorization` entier. Sans jeton configuré, la vue
répond `404` ; avec un mauvais jeton, `401` avec `WWW-Authenticate: Bearer`. Le jeton voyage seulement dans un en-tête,
jamais dans l'adresse, où les journaux et les mandataires le garderaient. Gardez-le dans une variable d'environnement ou
un secret, et donnez le même jeton à chaque moniteur qui a besoin des détails.

Le code d'état de la vue suit la disponibilité : `503` tant qu'une vérification est hors service.

## Une vérification en ligne de commande

```sh
bin/console phil:monitoring:health
```

affiche une ligne par vérification et l'état global, et échoue tant qu'une vérification est hors service.
