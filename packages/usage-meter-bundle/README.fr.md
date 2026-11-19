<!-- phil-dev source-sha256=bf982fd4ba1d17a5655463688f61f23ebe564c6da7ee7331e4296b0e1b48cf3d -->
# phil/usage-meter-bundle

**Des compteurs déclarés dans la configuration, gardés dans la base de données, et des limiteurs de débit qui
comptent sur eux.**

phil/usage-meter-bundle intègre `phil/usage-meter` à une application Symfony. Les compteurs sont déclarés dans la
configuration `phil_usage_meter`, chacun avec une limite et une période (jours, semaines, mois ou années du calendrier
dans un fuseau horaire, périodes de facturation à partir d'une ancre, ou fenêtre glissante en compartiments), et
vérifiés à la construction du conteneur. Les compteurs vivent dans deux tables d'une connexion Doctrine DBAL, qui
rejoignent le schéma que génère l'ORM pour qu'une migration les crée ; la mémoire d'un processus, ou un stockage de
l'application, conviennent aussi. Les fournisseurs de limites de l'application sont interrogés en chaîne, les
fonctionnalités des offres d'un abonné en dernier quand `phil/commerce-subscription-bundle` est installé, et un
fournisseur d'ancres donne à chaque sujet son propre jour de facturation.

Il s'adresse à toute application Symfony qui tient ses utilisateurs à des quotas : un niveau gratuit de trois
exportations par mois, une API qui permet tant d'appels par heure et par adresse, un forfait qui plafonne les jetons
d'une période de facturation. Ses limiteurs de débit sont des limiteurs de `phil/rate-limit-bundle` qui comptent sur
un compteur. `#[RateLimit('api')]`, une valeur par défaut de route ou une option de formulaire compte chaque requête
sur le compteur du limiteur, pour la clé de la requête : l'adresse du client ou l'utilisateur connecté, gardés en
empreintes à clé, ou la clé propre à l'application. Les limites d'un abonné et une période du calendrier s'appliquent
ainsi aux requêtes, et `phil/rate-limit-bundle` répond `429 Too Many Requests` avec `Retry-After` et ses en-têtes de
limite de débit quand le quota est atteint. `bin/console phil:usage-meter:purge`, exécuté une fois par jour, oublie les
compteurs qu'aucune fenêtre ne compte plus.

Il repose sur les composants que Symfony fournit déjà : Config et DependencyInjection pour la
configuration, HttpKernel pour le refus d'un `Idempotency-Key` mal formé, Console pour la purge.

## État

| Partie | État |
|---|---|
| Compteurs tirés de la configuration, chacun vérifié à la construction : limite, période du calendrier, fenêtre glissante, cycle de facturation | Implémenté et testé |
| Stockages : DBAL avec ses tables dans le schéma de l'ORM, en mémoire, ou un service de l'application | Implémenté et testé |
| Fournisseurs de limites en chaîne, limites tirées des fonctionnalités d'abonnement, un fournisseur d'ancres | Implémenté et testé |
| Limiteurs de débit de `phil/rate-limit-bundle` sur des compteurs : clés par adresse, par utilisateur ou par le résolveur de l'application, les limites du sujet | Implémenté et testé |
| Nouvelles tentatives comptées une fois par en-tête `Idempotency-Key`, sur option par limiteur, liées à la requête par un HMAC | Implémenté et testé |
| `phil:usage-meter:purge` | Implémenté et testé |
| Des fonctions Twig qui montrent ce qu'un sujet a utilisé et ce qu'il lui reste | Pas commencé : les gabarits lisent `UsageMeter::usage()` depuis le contrôleur |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les tables et la migration. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_usage_meter` : le stockage, les compteurs, les limites, les ancres. |
| [Limitation de débit](doc/fr/rate-limiting.md) | Les limiteurs sur des compteurs, leurs clés, `Idempotency-Key`, la réponse 429 et les en-têtes. |
| [Console](doc/fr/console.md) | `phil:usage-meter:purge` et sa planification. |
| [Points d'extension](doc/fr/extension-points.md) | Le stockage, les fournisseurs de limites et le fournisseur d'ancres de l'application. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lance le bundle, et quand. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester du code compté ou limité en débit. |
| [Référence](doc/fr/reference.md) | Chaque classe, chaque service et chaque constante du bundle. |

## Démarrage rapide

```sh
composer require phil/usage-meter-bundle
```

```yaml
# config/packages/phil_usage_meter.yaml
phil_usage_meter:
    meters:
        exports: { limit: 3, calendar: month, timezone: America/Toronto }
        requests: { limit: 600, rolling: 3600, buckets: 60 }
    rate_limiters:
        api: { meter: requests, key: ip }
```

```php
use Phil\UsageMeter\UsageMeter;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

final class ExportController
{
    #[RateLimit('api')]
    public function __invoke(UsageMeter $meter, string $accountId): Response
    {
        $meter->consume($accountId, 'exports'); // QuotaExceeded when the month's three are spent
        // ...
    }
}
```

```sh
bin/console doctrine:migrations:diff   # the two counter tables
bin/console phil:usage-meter:purge     # once a day
```

## Prérequis

PHP 8.5 ou plus récent, `phil/usage-meter`, `phil/rate-limit-bundle`, et les composants Config, DependencyInjection,
Console, HttpFoundation et HttpKernel de Symfony 8.2. `doctrine/dbal` pour le stockage en base de données,
`doctrine/orm` pour ses tables dans le schéma généré, `phil/commerce-subscription-bundle` pour les limites tirées des
fonctionnalités d'abonnement, chacun seulement s'il est utilisé.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
