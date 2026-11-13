<!-- phil-dev source-sha256=7d06e139300b6200f1034016e581dd10b621906fd99cb5cfc9b410d96a53403f -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-dom` et `ext-mbstring`.
- Symfony 8.2 ou une version plus récente : FrameworkBundle, TwigBundle et le composant Routing.
- Twig 3.21 ou une version plus récente et Symfony UX Twig Component 3.7 ou une version plus récente.
- Les recettes `card` et `badge` du kit PHIL UX, ou du kit Shadcn, installées par le Symfony UX Toolkit.

Facultatifs :

| Paquet | Pour |
|---|---|
| `phil/monitoring-bundle` | Les états des composants tirés de ses vérifications et de ses tâches planifiées. |
| `symfony/cache` | Les états tirés des vérifications, conservés un moment. |
| `league/flysystem` | Une cible `flysystem`. |
| `symfony/console` | `phil:status-page:push`. |
| `symfony/messenger`, `symfony/scheduler` | Un instantané publié dans un worker, à intervalle régulier. |
| `symfony/translation` | La page en anglais et en français. |

## Installer

```sh
composer require phil/status-page-bundle
```

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_status_page.yaml` avec un composant et
importe les routes dans `config/routes/phil_status_page.yaml`. Sans Flex :

```php
// config/bundles.php
Phil\StatusPageBundle\PhilStatusPageBundle::class => ['all' => true],
```

```yaml
# config/routes/phil_status_page.yaml
phil_status_page:
    resource: '@PhilStatusPageBundle/config/routes.php'
```

Les routes sont `/status`, `/status.json` et `/status.atom` ; un préfixe ou un hôte relève de l'application, donné à
l'import.

## Les recettes du kit

Les gabarits dessinent leurs parties avec des recettes du kit PHIL UX, installées dans l'application ; rien n'est rendu
depuis `vendor/` :

```sh
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Elles ont les noms, les propriétés et les blocs du kit Shadcn du Toolkit : un site sous Tailwind les installe donc
plutôt à partir du kit Shadcn. On les trouve sous le sous-répertoire des composants de la configuration `ux_toolkit` ;
dans un environnement où le Toolkit n'est pas activé, comme l'est souvent la production, répétez-le dans
`kit_subdirectory`.
