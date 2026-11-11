<!-- phil-dev source-sha256=1fd7e8a7297e41c0903cdb6a92fabdcc28fbd12b2b3f5d5b7815075b1e3190b9 -->
# phil/circuit-breaker-bundle

**Des circuits déclarés en configuration, leurs états partagés par les workers, et des clients HTTP derrière leurs
circuits.**

phil/circuit-breaker-bundle apporte `phil/circuit-breaker` à une application Symfony. Les circuits sont déclarés dans la
configuration `phil_circuit_breaker`, une politique par clé — un client HTTP, ou une destination comme un point de
terminaison de webhook — et vérifiés à la construction du conteneur. Leurs états vivent dans un pool de cache PSR-6
partagé par les *workers*, de sorte qu'un circuit ouvert dans l'un l'est dans tous, ou dans un stockage propre à
l'application. Un circuit doté de `http_client` place ce client derrière lui : tant que le circuit est ouvert, une
requête est refusée avant de partir, et ses réponses sont comptées — une erreur serveur ou une erreur de transport comme
un échec, tout le reste comme un succès.

Il s'adresse à toute application Symfony qui appelle des tiers qu'elle ne contrôle pas : un fournisseur de paiement, les
partenaires qu'atteignent ses webhooks, une API de géocodage. Un opérateur force l'ouverture d'un circuit par n'importe
quel service qui implémente `CircuitOverride`, étiqueté par l'autoconfiguration ; `phil/feature-flags-bundle` en fournit
un qui lit un indicateur par circuit. Le profileur montre l'état de chaque circuit à la fin de la requête.

Il est écrit de zéro, sur les composants que Symfony fournit déjà : Config et DependencyInjection pour la configuration,
Cache pour le stockage, HttpClient pour les clients décorés, HttpKernel pour le profileur.

## État

| Partie | État |
|---|---|
| Circuits issus de la configuration, chacun vérifié à la construction | Implémenté et testé |
| États dans un pool de cache PSR-6, ou dans un stockage propre à l'application | Implémenté et testé |
| Un client HTTP derrière son circuit, par client ou par destination | Implémenté et testé |
| Forçages étiquetés et interrogés ensemble, autoconfigurés | Implémenté et testé |
| Un panneau du profileur pour les circuits | Implémenté, son collecteur testé |
| Commandes de console pour inspecter ou réinitialiser un circuit | Pas commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, le pool de cache. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_circuit_breaker`, et les erreurs d'un circuit mal formé. |
| [Clients HTTP](doc/fr/http-clients.md) | Un client derrière son circuit : ce qui est refusé, ce qui compte comme un échec. |
| [Points d'extension](doc/fr/extension-points.md) | Le stockage des états, les forçages, le paramètre des clés de circuit. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application dotée de circuits. |
| [Référence](doc/fr/reference.md) | Chaque classe, service, étiquette, paramètre et gabarit du bundle. |

## Démarrage rapide

```sh
composer require phil/circuit-breaker-bundle
```

```yaml
# config/packages/phil_circuit_breaker.yaml
phil_circuit_breaker:
    circuits:
        http.payments:
            failure_rate_percent: 50
            minimum_calls: 10
            window_seconds: 60
            open_seconds: 30
            http_client: payments.client
```

```php
use Phil\CircuitBreakerBundle\Infrastructure\HttpClient\Exception\CircuitOpen;

try {
    $response = $paymentsClient->request('POST', '/charges', ['json' => $charge]);
} catch (CircuitOpen) {
    // the circuit is open: the request was not sent
}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent, et `phil/circuit-breaker`. `psr/cache` pour le stockage par défaut,
`symfony/http-client` pour les clients derrière leurs circuits, `symfony/web-profiler-bundle` pour le panneau — chacun
seulement s'il est utilisé.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
