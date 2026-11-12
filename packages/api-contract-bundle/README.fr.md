<!-- phil-dev source-sha256=03bc4b498adc3009971d242b07819d6585433035b137239398f8fd1a511c5156 -->
# phil/api-contract-bundle

**Des détails de problème, des requêtes idempotentes rejouées, des avis d'obsolescence et la vérification OpenAPI,
branchés dans une application Symfony.**

phil/api-contract-bundle apporte dans Symfony les deux côtés du contrat d'une API HTTP sans version. Comme serveur, il
répond aux exceptions par des détails de problème RFC 9457. Les contrôleurs marqués `#[Idempotent]` s'exécutent
une fois par clé, par la garde de `phil/idempotency`, et rejouent leurs réponses. Les contrôleurs marqués
`#[DeprecatedEndpoint]` annoncent `Deprecation` et `Sunset`. Comme client, il consigne les obsolescences des API
qu'appelle l'application, les journalise une fois par jour et les énumère avec une commande qui échoue en intégration
continue tant que l'une d'elles a une date de retrait. Une seconde commande vérifie le document OpenAPI de l'application
selon un niveau de règles et échoue en intégration continue quand un constat atteint la gravité choisie.

Il s'adresse aux applications Symfony qui publient une API REST et JSON sans numéro de version, ou qui appellent des API
qui annoncent leur propre retrait. Les règles elles-mêmes se trouvent dans `phil/api-contract` et `phil/idempotency`, de
simples bibliothèques PHP. Utilisez ces bibliothèques seules hors de Symfony, ou lorsque vous voulez les valeurs et les
décisions sans le branchement.

Le bundle se branche au noyau HTTP par des écouteurs sur ses événements de contrôleur, de réponse et d'exception, et
enveloppe le client HTTP de Symfony. Doctrine DBAL, ou un pool de cache sous un verrou Symfony, conserve les clés
d'idempotence, Symfony Security les rattache à l'utilisateur, le composant Console exécute les deux commandes et le
composant Yaml lit un document YAML ; chacun de ces éléments est facultatif. La conception implémente les ports de la
bibliothèque avec des adaptateurs pour chacun de ces composants.

## État

| Partie | État |
|---|---|
| `ProblemDetailsListener`, `ProblemException`, `ProblemDetailsResponse` | Implémenté et testé |
| `#[Idempotent]`, `IdempotencyListener`, `CallerScopeResolver` | Implémenté et testé |
| Le stockage d'idempotence de `phil/idempotency` sur une connexion Doctrine ou un pool de cache, l'écouteur de schéma | Implémenté et testé |
| `#[DeprecatedEndpoint]`, `DeprecationListener` | Implémenté et testé |
| `DeprecationObservingHttpClient`, `CacheObservedDeprecations`, `phil:api-contract:deprecations` | Implémenté et testé |
| `phil:api-contract:verify`, le service vérificateur, les règles propres à l'application par étiquette | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, la table. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, les services, le remplacement d'un port. |
| [Problèmes](doc/fr/problems.md) | Les exceptions sous forme de détails de problème. |
| [Idempotence](doc/fr/idempotency.md) | `#[Idempotent]`, les réponses et leurs problèmes, la portée, le stockage. |
| [Obsolescences](doc/fr/deprecations.md) | `#[DeprecatedEndpoint]` côté serveur ; le client HTTP, le registre et la commande côté client. |
| [Vérification](doc/fr/verification.md) | `phil:api-contract:verify`, ses options et ses codes de sortie, les règles propres à l'application, l'intégration continue. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

Le contrat lui-même — membres d'un problème, en-têtes, niveaux et règles de vérification — est documenté avec
`phil/api-contract`, et les clés, les empreintes et la décision avec `phil/idempotency`.

## Démarrage rapide

```yaml
phil_api_contract:
    problem_details:
        paths: ['^/api/']
    verification:
        document: '%kernel.project_dir%/openapi.yaml'
```

```php
#[Route('/api/orders', methods: ['POST'])]
#[Idempotent]
public function create(Request $request): Response { /* runs once per key */ }

#[Route('/api/v0/orders', methods: ['GET'])]
#[DeprecatedEndpoint(deprecatedAt: '2026-11-01', sunsetAt: '2027-05-01', link: 'https://example.com/changes')]
public function legacy(): Response { /* … */ }

throw ProblemException::of(409, 'This order was already shipped.');
```

```bash
bin/console phil:api-contract:verify --level=strict
```

## Prérequis

PHP 8.5 ou plus récent ; `phil/api-contract` 0.1 ; `psr/clock` 1.0 ; les composants dependency injection, config, HTTP
foundation et HTTP kernel de Symfony 8.2. Doctrine DBAL et ORM avec DoctrineBundle, le client HTTP de Symfony, un cache
PSR-6, PSR-3, Symfony Console, Security et Yaml sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
