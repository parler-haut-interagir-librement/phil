<!-- phil-dev source-sha256=9753a155d213a3c65f0914eec28f680eb76181d5f9d2ac113e9aa3c1f9bf4c81 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 avec FrameworkBundle et sa protection CSRF (`symfony/security-csrf`), DoctrineBundle
avec DBAL 4 et ORM 3, un service `clock` (`symfony/clock`), et `phil/ujo`. `phil/ujo-bundle` est recommandé : les blocs
enregistrés sont alors vérifiés avec les types de blocs propres à l'application. Pour pousser la présence et les
enregistrements, MercureBundle et un hub Mercure. Dans le navigateur, Stimulus 3 servi par `symfony/stimulus-bundle` et
AssetMapper, ainsi que l'éditeur de blocs de `phil/block-editor-bundle`.

## Activer le bundle

```sh
composer require phil/edit-lock-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\EditLockBundle\PhilEditLockBundle::class => ['all' => true],
];
```

## Les tables

Le bundle associe `Phil\EditLockBundle\Domain\Model\Lease` et `LeasedResource` pour Doctrine, sous la forme des tables
`phil_edit_lease` et `phil_edit_lease_resource` : créez-les avec les migrations de l'application.

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Colonne | Type | Rôle |
|---|---|---|
| `resource` | `VARCHAR(255)`, clé primaire | La ressource, telle que l'application la nomme |
| `part` | `VARCHAR(64)`, clé primaire | L'identifiant du bloc ; vide pour la ressource entière |
| `holder_id`, `holder_name` | `VARCHAR(255)` | Qui le détient, et le nom que voient les autres |
| `acquired_at`, `expires_at` | `DATETIME`, UTC | Depuis quand et jusqu'à quand ; `expires_at` est indexée |
| `version` | `INTEGER` | Augmentée de un à chaque modification, pour la comparaison et échange |

`phil_edit_lease_resource` contient une ligne par ressource ayant déjà fait l'objet d'un bail, la ligne que verrouille
chaque revendication ([concurrence](leases.md#concurrence)) :

| Colonne | Type | Rôle |
|---|---|---|
| `resource` | `VARCHAR(255)`, clé primaire | La ressource, telle que l'application la nomme |
| `claims` | `INTEGER` | Le nombre de revendications exécutées sur elle |

## Les services de l'application

Les points de terminaison ne répondent qu'une fois que l'application a dit qui écrit et ce que chacun peut modifier :

```yaml
# config/packages/phil_edit_lock.yaml
phil_edit_lock:
    holder_provider: App\Collaboration\CurrentWriter
    policy: App\Collaboration\WritersPolicy
```

```php
use Phil\EditLockBundle\Application\HolderProvider;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CurrentWriter implements HolderProvider
{
    public function __construct(private Security $security) {}

    public function holder(): ?Holder
    {
        $user = $this->security->getUser();

        return null === $user ? null : Holder::of($user->getUserIdentifier(), $user->getUserIdentifier());
    }
}
```

La politique est décrite dans [baux](leases.md), le magasin de blocs dans [blocs](blocks.md).

## Les routes

```yaml
# config/routes/phil_edit_lock.yaml
phil_edit_lock:
    resource: '@PhilEditLockBundle/config/routes.php'
    prefix: /_edit-lock
```

Placez le préfixe derrière le pare-feu de l'application. Les routes sont énumérées dans [points de
terminaison](endpoints.md). Le bundle ajoute le jeton `phil_edit_lock` aux `csrf_protection.stateless_token_ids` de
FrameworkBundle, ce qui active `framework.csrf_protection` : laissez-la active. Avec `csrf_protection: false`, le
conteneur s'arrête sur une `LogicException` qui l'indique.

## Mercure

Lorsque MercureBundle est enregistré, le bundle publie vers son hub par défaut. Les mises à jour sont privées : le
navigateur de chaque rédacteur a besoin du droit de s'abonner au sujet de la ressource, que l'application accorde comme
l'explique MercureBundle, par exemple avec son témoin d'autorisation :

```php
$authorization->setCookie($request, [$config['topic']]); // the topics this writer may subscribe to
```

[Mercure](mercure.md) présente les sujets, les messages et les options.

## Le contrôleur et la feuille de style

Avec AssetMapper, le bundle associe son contrôleur sous `@phil/edit-lock-bundle` et sa feuille de style sous
`phil-edit-lock`. Activez le contrôleur dans `assets/controllers.json` :

```json
{
    "controllers": {
        "@phil/edit-lock-bundle": {
            "collaboration": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

```twig
<link rel="stylesheet" href="{{ asset('phil-edit-lock/edit-lock.css') }}">
```

[JavaScript](javascript.md) montre le balisage et la configuration.
