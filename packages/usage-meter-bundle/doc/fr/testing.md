<!-- phil-dev source-sha256=387a2953eab6711ec2e81a810063a82c9f3758e573c103373ce3a9c46a61b711 -->
# Tests

## Les tests du bundle

`tests/Unit` vérifie la configuration et les services qu'elle donne, les compteurs construits à partir d'elle et les
refus des mauvais, le comptage des limiteurs de débit sur leurs compteurs et leurs décisions, les nouvelles tentatives
comptées une fois par `Idempotency-Key` et les clés mal formées refusées, et la commande de purge.
`tests/Functional` démarre une petite application (FrameworkBundle, DoctrineBundle avec l'ORM sur SQLite,
`phil/rate-limit-bundle` et ce bundle). Il crée les tables de compteurs à partir du schéma que génère l'ORM, envoie des
requêtes à des actions limitées en débit jusqu'à ce qu'elles soient refusées, et exécute la purge.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/usage-meter-bundle/tests
```

Le bundle est tenu à un score de mutation couverte de 100 % par Infection, comme la bibliothèque.

## Tester une application

Dans l'environnement de test, gardez les compteurs en mémoire et le temps en main :

```yaml
# config/packages/phil_usage_meter.yaml
when@test:
    phil_usage_meter:
        store: memory
        clock: test.clock
```

```yaml
when@test:
    services:
        test.clock:
            class: Symfony\Component\Clock\MockClock
            public: true
```

Chaque démarrage du noyau commence avec des compteurs vides. Le test d'une action limitée en débit envoie des requêtes
jusqu'au 429, puis avance l'horloge au-delà de `Retry-After` et en envoie une de plus. Le test d'un quota appelle
`UsageMeter::consume()` depuis le conteneur, ou vérifie `usage()` après l'action.
