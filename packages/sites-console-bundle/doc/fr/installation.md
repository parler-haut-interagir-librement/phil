<!-- phil-dev source-sha256=45baefdab42789f0bb0dc6c461531bc4023cb35f9229494f14fa03aed08f9c93 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-sodium` et `ext-mbstring`.
- Symfony 8.2 ou une version plus récente : FrameworkBundle avec le composant Lock, et le composant Routing.

Sur la console, aussi :

- SecurityBundle, avec un pare-feu devant les écrans dont les utilisateurs portent les rôles de la console, et la
  protection CSRF.
- TwigBundle, Twig 3.31.1 ou une version plus récente et Symfony UX Twig Component 3.8 ou une version plus récente.
- Le composant HttpClient.
- DoctrineBundle avec l'ORM, pour les tables de la console.
- Les recettes `badge`, `button`, `card` et `table` du kit PHIL UX, ou du kit Shadcn, installées par le Symfony UX
  Toolkit.

Facultatifs :

| Paquet | Pour |
|---|---|
| `phil/monitoring-bundle` | La santé de chaque site, lue dans ses vérifications. |
| `symfony/messenger` | Les interrogations, poussées et plans de mise à jour confiés à un worker depuis les écrans. |
| `symfony/translation` | Les écrans en anglais et en français. |

## Installer

```sh
composer require phil/sites-console-bundle
```

Avec Symfony Flex, la recette enregistre le bundle et écrit `config/packages/phil_sites_console.yaml` et
`config/routes/phil_sites_console.yaml`, les deux moitiés en commentaire. Sans Flex :

```php
// config/bundles.php
Phil\SitesConsoleBundle\PhilSitesConsoleBundle::class => ['all' => true],
```

## Clés

Chaque côté a sa propre paire de clés Ed25519. Générez-en une pour la console et une pour chaque site :

```sh
php bin/console phil:sites-console:keys
```

La commande affiche une clé privée et sa clé publique et n'écrit rien. Gardez chaque clé privée dans une variable
d'environnement ou un secret de sa propre application ; donnez la clé publique de la console à chaque site, et la clé
publique de chaque site à la console quand le site est enregistré.

## Sur chaque site : l'agent

```yaml
# config/packages/phil_sites_console.yaml
phil_sites_console:
    agent:
        enabled: true
        site_id: shop
        private_key: '%env(SITES_CONSOLE_AGENT_KEY)%'
        console_public_key: '%env(SITES_CONSOLE_PUBLIC_KEY)%'
```

```yaml
# config/routes/phil_sites_console.yaml
phil_sites_console_agent:
    resource: '@PhilSitesConsoleBundle/config/routes/agent.php'
    prefix: /_sites-console
```

Les points de terminaison ne répondent qu'à la console, par signature : ils n'ont besoin ni de pare-feu ni de session.
Gardez-les hors de tout pare-feu qui demanderait une connexion. L'agent garde ses nonces et ses clés d'idempotence dans
`cache.app` par défaut : sur un site à plusieurs serveurs, nommez un pool qu'ils partagent dans `agent.cache_pool`, et une fabrique de verrous sur un magasin qu'ils partagent dans
`agent.lock_factory`. Les clés de la console
qu'une rotation dépose et retire vont dans `agent.key_pool`, ou dans ce pool : choisissez-en un qui survit aux
déploiements, comme Redis ou une base de données.

## Sur la console

```yaml
# config/packages/phil_sites_console.yaml
phil_sites_console:
    console:
        enabled: true
        private_key: '%env(SITES_CONSOLE_PRIVATE_KEY)%'
```

```yaml
# config/routes/phil_sites_console.yaml
phil_sites_console:
    resource: '@PhilSitesConsoleBundle/config/routes/console.php'
    prefix: /admin/sites
```

Placez le préfixe derrière un pare-feu. Les écrans demandent `ROLE_SITES_CONSOLE_VIEWER` pour lire et
`ROLE_SITES_CONSOLE_OPERATOR` pour agir ; le pare-feu les donne à partir du fournisseur d'identité — par exemple la
revendication des rôles d'un jeton OpenID Connect, associée par l'authentificateur de l'application. Voir
[Modèle de sécurité](security-model.md#qui-peut-lire-et-agir).

### Les tables

Le bundle associe ses six tables à Doctrine quand la console est activée avec son dépôt Doctrine :
`phil_sites_console_site`, `phil_sites_console_snapshot`, `phil_sites_console_push`, `phil_sites_console_update_plan`,
`phil_sites_console_journal` et `phil_sites_console_key_rotation`. Générez la migration pour MySQL, MariaDB ou PostgreSQL avec :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Les recettes du kit

Les écrans dessinent leurs parties avec des recettes du kit PHIL UX, installées dans l'application ; rien n'est rendu
depuis `vendor/` :

```sh
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Elles ont les noms, les props et les blocs du kit Shadcn du Toolkit : un site sur Tailwind les installe donc plutôt
depuis le kit Shadcn. On les trouve sous le sous-répertoire des composants de la configuration `ux_toolkit` ; dans un
environnement où le Toolkit n'est pas activé, comme l'est souvent la production, répétez-le dans
`console.kit_subdirectory`.

## Les deux dans une seule application

Une console qui est aussi un site active les deux moitiés et importe les deux fichiers de routes.
