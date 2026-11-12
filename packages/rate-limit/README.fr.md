<!-- phil-dev source-sha256=e7a143bf26af123d4c5c323b49a9ea1250f3b6fe5979e386c544ed50bd8dc193 -->
# phil/rate-limit

**La garde de production des limites de débit sur `symfony/rate-limiter` : un conteneur qui bâtirait une limite qui ne
limite rien refuse de se bâtir.**

Une limite de débit compte dans un magasin. Quand ce magasin est un pool de cache ou un stockage que chaque serveur
garde pour lui (le pool sur système de fichiers `cache.app` en est un par défaut), une application derrière un
répartiteur de charge compte à part sur chaque serveur, et un client peut épuiser la limite une fois par serveur. Une
limite activée sans que `symfony/rate-limiter` soit installé n'échoue qu'à la première requête qui en a besoin.

phil/rate-limit fait de ces deux cas des erreurs de construction du conteneur :

- `RateLimitGuard::requireComponent()` refuse une limite activée sans le composant, et nomme l'option qui la coupe ;
- `RateLimitGuard::watch()` note chaque magasin qu'un bundle configure, et la passe de compilation `RateLimitGuard`
  refuse, dans l'environnement `prod`, celui que `StoreLocality` juge local au serveur : un pool sur l'adaptateur
  filesystem, APCu, array ou system de `symfony/cache`, le `InMemoryStorage` de `symfony/rate-limiter`, ou un
  `CacheStorage` qui reçoit un tel pool. Une application sur un seul serveur le dit, et ses magasins ne sont pas notés.

Elle ne lit que les définitions de services : limiter reste le travail de `symfony/rate-limiter`, que la bibliothèque
n'enveloppe ni ne remplace. Plusieurs bundles partagent un seul paramètre caché et une seule vérification, quel que soit
le nombre d'entre eux qui ajoutent la passe.

C'est une simple bibliothèque PHP dont les prérequis sont `symfony/config` et `symfony/dependency-injection` ; les
composants cache et rate limiter sont nommés par leurs classes et n'ont pas à être installés.

## État

| Partie | État |
|---|---|
| Une limite refusée sans `symfony/rate-limiter` | Implémenté et testé |
| Magasins notés par chemin de configuration, `single_server`, l'option qui coupe une limite | Implémenté et testé |
| Pools locaux par adaptateur ou par classe, `InMemoryStorage`, `CacheStorage` suivi jusqu'à son pool, alias et parents suivis, avec une borne | Implémenté et testé |
| La vérification en `prod` seulement, une fois pour tous les bundles | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, ajout de la garde à un bundle. |
| [La garde](doc/fr/guard.md) | Ce qui est refusé, quand, les messages, ce qui est local et ce qui est partagé. |
| [Points d'extension](doc/fr/extension-points.md) | Utiliser la garde depuis un bundle, juger soi-même de la localité. |
| [Tests](doc/fr/testing.md) | Les tests du paquet. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/rate-limit
```

```php
use Phil\RateLimit\RateLimitGuard;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class AcmeBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RateLimitGuard());
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        RateLimitGuard::requireComponent(interface_exists(RateLimiterFactoryInterface::class), 'acme.rate_limit');
        RateLimitGuard::watch($builder, $config['rate_limit']['cache_pool'], 'acme.rate_limit.cache_pool', $config['rate_limit']['single_server'], 'enabled');
        // ... the CacheStorage and the RateLimiterFactory of symfony/rate-limiter
    }
}
```

## Prérequis

PHP 8.5 ou plus récent, `symfony/config` et `symfony/dependency-injection` 8.2 ou plus récents.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
