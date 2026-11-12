<!-- phil-dev source-sha256=7088f433e5dfcc3da835e0e84a3a6c2cb14f3eeb289f56b598926f71b465dffb -->
# Installation

```sh
composer require phil/rate-limit
```

Le paquet demande PHP 8.5 ou plus récent, `symfony/config` et `symfony/dependency-injection` 8.2 ou plus récents. Il
suggère `symfony/rate-limiter`, dont il garde les limiteurs ; ni le rate limiter ni `symfony/cache` ne sont requis,
puisque la bibliothèque nomme leurs classes sans les charger.

## Dans un bundle

Un bundle qui bâtit des limites de débit ajoute la passe une fois, dans `build()`, et note ses magasins pendant qu'il
charge sa configuration :

```php
public function build(ContainerBuilder $container): void
{
    $container->addCompilerPass(new RateLimitGuard());
}
```

Chaque bundle peut ajouter la passe : la première à s'exécuter vérifie les magasins de tous, et les autres ne trouvent
plus rien. Voir [La garde](guard.md) pour ce que prennent `requireComponent()` et `watch()`.

## Dans une application

Une application n'a rien à installer à la main : les bundles qui ont besoin de la garde requièrent la bibliothèque. Sur
un seul serveur, elle règle l'option `single_server` que ces bundles offrent, et garde son pool local.
