<!-- phil-dev source-sha256=5c578c15b8a6db09ebb0170e2572a4f33b5a3632dcf8e81e56d0730d53015c78 -->
# Tests

## Les tests du bundle

`tests/Unit` vérifie la configuration et les services qu'elle produit — le stockage par défaut et son pool, le stockage
et l'horloge de l'application, les clients décorés, les forçages étiquetés et leur autoconfiguration, le paramètre des
clés, le collecteur — et les refus des circuits mal formés ; un conteneur compilé où un forçage autoconfiguré force
l'ouverture d'un circuit ; le stockage en cache et les entrées d'une autre forme qu'il lit comme fermées ; un client
derrière son circuit par l'intermédiaire de `MockHttpClient`, qui compte les erreurs serveur et de transport, refuse
tant que le circuit est ouvert et le referme après un appel d'essai ; et le collecteur du profileur.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/circuit-breaker-bundle/tests
```

Le bundle est tenu par Infection à un score de mutation couverte de 100 %, comme la bibliothèque.

## Tester une application

Dans l'environnement de test, gardez les états dans un pool propre au test et le temps sous votre contrôle :

```yaml
when@test:
    framework:
        cache:
            pools:
                cache.circuits:
                    adapter: cache.adapter.array

    phil_circuit_breaker:
        cache_pool: cache.circuits
        clock: test.clock

    services:
        test.clock:
            class: Symfony\Component\Clock\MockClock
            public: true
```

Un test déclenche un circuit avec des réponses `503` de `MockHttpClient`, vérifie que la requête suivante lève
`CircuitOpen` sans atteindre le *mock*, puis avance l'horloge au-delà de `open_seconds` et envoie une requête d'essai.
