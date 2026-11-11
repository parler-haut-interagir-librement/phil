<!-- phil-dev source-sha256=d5e204652183bd95bfe53664d4210bb431c6c84d6cf7405f552c684298e3c7cf -->
# Tests

## Les tests du bundle

`tests/Unit` vérifie les objets-valeurs et leurs refus ; le vérificateur, son chronométrage sur une horloge simulée et
sa façon de traiter une vérification qui lance une exception ; les battements, l'interrupteur d'homme mort, ses alertes
une fois par retard, aux destinataires administrateurs, ses nouvelles tentatives quand une alerte échoue et son verrou
entre deux rondes concurrentes ; le service de maintenance et le modèle de lecture ; les files d'attente sur des
transports en mémoire ; chaque vérification du bundle contre SQLite, des pools en tableau, un répertoire temporaire et
un stockage en mémoire ; les stockages et les enregistrements d'une autre forme qu'ils lisent ; les points de
terminaison, l'écouteur de la maintenance et ses listes d'autorisation, la page des files d'attente et ses actions ; les
commandes ; l'écouteur de Scheduler et le *handler* de veille ; et la configuration, les services qu'elle produit et la
passe de compilation.

`tests/Functional` démarre une petite application — FrameworkBundle avec des transports Messenger en mémoire, Twig, une
horloge figée — et parcourt les points de terminaison, le mode maintenance de la commande jusqu'au visiteur, une tâche
en retard de la veille jusqu'à la disponibilité, et le message de veille réparti sur le bus.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/monitoring-bundle/tests
```

Le bundle doit atteindre un score de mutation couvert de 100 % avec Infection.

## Tester une application

Dans l'environnement de test, gardez les battements dans un pool en tableau, l'indicateur de maintenance dans le
répertoire du test, et l'heure sous contrôle :

```yaml
when@test:
    framework:
        cache:
            pools:
                cache.monitoring:
                    adapter: cache.adapter.array
    phil_monitoring:
        cache_pool: cache.monitoring
        maintenance:
            file: '%kernel.cache_dir%/maintenance.json'
    services:
        clock:
            class: Symfony\Component\Clock\MockClock
            arguments: ['2026-11-08 10:00:00 UTC']
            public: true
```

Un test émet alors le battement d'une tâche avec `Heartbeats`, avance l'horloge, et vérifie ce que répond
`MonitoringStatus` ou `/_health/ready`. Une vérification de l'application est une classe ordinaire : testez-la seule,
puis une fois par `HealthChecker`.
