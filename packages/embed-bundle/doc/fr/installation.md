<!-- phil-dev source-sha256=f1cf228f6174964b67065d32a4e49438b46d94e8e28bf6177fd019039b14e756 -->
# Installation

```sh
composer require phil/embed-bundle
```

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_embed.yaml` avec une clé lue dans
`EMBED_KEY`, ajoute cette variable à `.env` et importe la route du script sous `/_embed`. Sans elle, enregistrez le
bundle dans `config/bundles.php` :

```php
Phil\EmbedBundle\PhilEmbedBundle::class => ['all' => true],
```

et importez la route :

```yaml
# config/routes/phil_embed.yaml
phil_embed:
    resource: '@PhilEmbedBundle/config/routes.php'
    prefix: /_embed
```

## La clé

La valeur de `EMBED_KEY` dans la recette est un substitut. Donnez à chaque environnement son propre secret de 32 octets
ou plus, hors du dépôt — un secret Symfony, ou l'environnement du serveur :

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
php bin/console secrets:set EMBED_KEY
```

## Le contrôleur du cadre

Avec AssetMapper, le contrôleur du cadre est servi par le bundle sous le nom `@phil/embed-bundle/frame_controller.js`,
sans étape de construction ; Symfony Flex l'ajoute à `assets/controllers.json` à partir du `assets/package.json` du
bundle. La page intégrée le démarre depuis son élément `<html>` :

```twig
<html lang="{{ app.request.locale }}" {{ phil_embed_frame_attributes() }}>
```

Le script que chargent les autres sites n'est pas une ressource du site : le bundle le sert sous sa propre route, à une
adresse stable.

## Paquets facultatifs

| Paquet | Pour |
|---|---|
| `phil/browser-security-bundle` | `frame-ancestors` écrit dans la politique de sécurité du contenu du site, et `X-Frame-Options` retiré de celle-ci |

## Prérequis

PHP 8.5 ou une version plus récente avec l'extension sodium, Symfony 8.2 ou une version plus récente.
