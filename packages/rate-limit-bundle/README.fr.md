<!-- phil-dev source-sha256=6f884c89f3ae6be50bcb6cef0b40aa71c0eebd748399fdc30216694123c84077 -->
# phil/rate-limit-bundle

**Des limites de débit déclarées sur les contrôleurs, les routes et les formulaires Symfony.**

phil/rate-limit-bundle soumet les requêtes à des limiteurs nommés : `#[RateLimit]` sur un contrôleur ou une action, la
valeur par défaut de route `_rate_limit`, et l'option `rate_limit` d'un formulaire. Les limiteurs sont les fenêtres
fixes, les fenêtres glissantes, les seaux à jetons et les composés de `symfony/rate-limiter`, déclarés dans la
configuration du bundle avec leur stockage, ou ceux d'autres paquets. Chaque requête compte sous une clé (l'adresse du
client, l'utilisateur connecté, l'adresse et la route, ou celle de l'application), toujours une empreinte à clé, jamais
une adresse en clair. Un refus reçoit la réponse `429 Too Many Requests` avec `Retry-After` et les champs
`RateLimit-Policy` et `RateLimit` du brouillon de l'IETF : un problème en JSON pour une API, une page Twig pour un
navigateur, une erreur sur le formulaire. Chaque refus est réparti comme un événement.

## État

| Partie | État |
|---|---|
| `#[RateLimit]` sur les classes et les actions, la valeur par défaut de route `_rate_limit` | Implémenté et testé |
| Les limiteurs à fenêtre fixe, à fenêtre glissante, à seau de jetons, composés et par fabrique, vérifiés à la construction | Implémenté et testé |
| Les clés `ip`, `user`, `user_or_ip`, `ip_route` et celles de l'application, le réseau avec phil/ip-anonymizer | Implémenté et testé |
| `429` avec `Retry-After`, les en-têtes `RateLimit` du brouillon ou anciens, le problème en JSON, la page Twig en anglais et en français | Implémenté et testé |
| Les soumissions de formulaire limitées par nom de formulaire | Implémenté et testé |
| L'événement `RateLimitExceeded`, les limiteurs d'autres paquets par étiquette | Implémenté et testé |
| Les limiteurs en observation : l'événement `RateLimitObserved` et un avertissement, sans refus | Implémenté et testé |
| Un stockage propre à chaque serveur refusé en `prod`, avec la garde de phil/rate-limit | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle, le stockage. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Limiter les requêtes](doc/fr/rules.md) | L'attribut, la valeur par défaut de route, l'ordre des règles. |
| [Clés](doc/fr/keys.md) | Pour qui compte une requête, et comment les clés sont hachées. |
| [Réponses](doc/fr/responses.md) | Le `429`, ses en-têtes, le problème en JSON, la page, l'événement. |
| [Formulaires](doc/fr/forms.md) | Limiter les soumissions d'un formulaire par son nom. |
| [Points d'extension](doc/fr/extension-points.md) | Les limiteurs, les stratégies de clé, l'événement, le gabarit. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque fichier du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\RateLimitBundle\PhilRateLimitBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_rate_limit.yaml
phil_rate_limit:
    cache_pool: cache.rate_limits
    limiters:
        api: { policy: sliding_window, limit: 100, interval: '1 minute' }
        reset: { policy: fixed_window, limit: 5, interval: '15 minutes', key: ip_route }
        contact: { policy: token_bucket, limit: 3, rate: { interval: '10 minutes' } }
    forms:
        contact: contact
```

```php
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

#[RateLimit('api')]
final class SearchController
{
    #[RateLimit('reset')]
    public function resetPassword(): Response
}
```

Un formulaire de connexion est limité par Symfony lui-même : `security.firewalls.<name>.login_throttling` compte chaque
tentative avant la vérification du mot de passe et regroupe les adresses IPv6 par /64. Le bundle limite tout le reste :
les API, les autres formulaires, les contrôleurs et les routes. Voir [Formulaires de connexion](doc/fr/configuration.md#formulaires-de-connexion).

## Prérequis

PHP 8.5 ou plus récent et Symfony 8.2 ou plus récent avec FrameworkBundle ; TwigBundle pour la page de refus, le
composant de formulaire pour les formulaires, la sécurité pour les clés d'utilisateur, phil/ip-anonymizer pour les clés
de réseau, un journal PSR-3 pour les avertissements d'un limiteur en observation. phil/rate-limit, requis, garde les
stockages en production.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
