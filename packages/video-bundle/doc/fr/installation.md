<!-- phil-dev source-sha256=1dfa247547cbfcffe1b7510c56c02ba7e1cb3cf8c64582fa8af80dbdec79bf2f -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récente | Classes en lecture seule |
| `phil/timed-text` | `^0.1` | Les transcriptions et les chapitres, et le WebVTT servi |
| `phil/url` | `^0.1` | Les adresses des fichiers, des affiches et des pistes ; les lecteurs de YouTube et de Vimeo sans témoin |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-foundation` | `^8.2` | Le bundle, sa configuration, la route des visionnements |
| `symfony/service-contracts`, `psr/event-dispatcher`, `psr/cache` | | La réinitialisation du stockage en mémoire, `VideoProgressed`, le stockage en cache |
| `symfony/ux-twig-component` | `^3.7`, facultatif | Le composant du lecteur |
| `phil/ux-kit` | `^0.5`, facultatif | Les recettes Video, Image et Button, et `image_origin()`, avec lesquelles le lecteur dessine |

## Activer le bundle

```sh
composer require phil/video-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\VideoBundle\PhilVideoBundle::class => ['all' => true],
];
```

## Les recettes du kit

Le lecteur est dessiné avec les recettes Video — qui appelle la recette Image pour sa figure — et Button du kit PHIL UX,
installées par le Symfony UX Toolkit, et avec `image_origin()` du bundle de phil/ux-kit, qui refuse un fichier d'un hôte
que le site ne liste pas :

```sh
composer require symfony/ux-twig-component phil/ux-kit
for recipe in video image button; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

```yaml
# config/packages/phil_ux_kit.yaml
phil_ux_kit:
    image:
        hosts: [cdn.example.com]   # the hosts of the files, posters and tracks
```

Les recettes ont les noms, les propriétés et les blocs du kit Shadcn ; un site sur Tailwind installe donc celles du kit
Shadcn et ses extras. Elles se trouvent sous le sous-répertoire des composants de la configuration `ux_toolkit` ; dans un
environnement où le Toolkit n'est pas activé, répétez-le dans `kit_subdirectory`.

## Le contrôleur Stimulus

`phil--video-bundle--player` est livré dans `assets/` avec sa feuille de style, et a besoin de `@hotwired/stimulus` 3.

## La route des visionnements

Avec le suivi activé, importez la route à laquelle le lecteur envoie les visionnements :

```yaml
# config/routes/phil_video.yaml
phil_video:
    resource: '@PhilVideoBundle/config/routes.php'
```

Elle répond à `POST /_phil/video/progress`, sans état ; `tracking.endpoint` est ce chemin.

## Les lecteurs des plateformes

Une vidéo liée charge `https://www.youtube-nocookie.com` ou `https://player.vimeo.com` dans un cadre une fois le bouton
pressé : une politique de sécurité du contenu les liste dans `frame-src`.
