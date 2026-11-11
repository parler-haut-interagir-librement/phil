<!-- phil-dev source-sha256=ff865a1b89ed0cd25b21498ca791cbe30070854f9b856a67aebb51802ca7429e -->
# phil/geo-bundle

**Des coordonnées stockées, des distances dans Twig, des géocodeurs configurés et des listes triées du plus proche au
plus loin pour les applications Symfony.**

`phil/geo-bundle` intègre `phil/geo` à une application Symfony. Il mappe `Coordinates` comme un objet intégrable
Doctrine de deux colonnes `float`, ajoute `CoordinatesType` pour saisir une position en deux champs et donne à Twig
`geo_distance()`, `format_distance`, `geo_attributes()` et `geo_schema()`. Il enregistre les géocodeurs que liste la
configuration, interrogés dans cet ordre et mis en cache au besoin, ainsi qu'un localisateur IP sur MaxMind ou ipstack.
La commande `phil:geo:maxmind:update` télécharge la plus récente base MaxMind, la vérifie et la met en place.

Il s'adresse aux applications Symfony où des rédacteurs saisissent des positions et où des visiteurs cherchent le lieu
le plus proche. Le contrôleur Stimulus `distance-sort` trie les lieux selon leur distance au visiteur **dans le
navigateur**. Le navigateur demande sa position au visiteur, et cette position n'atteint jamais le serveur. Le serveur
publie les lieux, brouillés quand leur position exacte est privée, et ne sait rien de qui est près de quoi.

Utilisez le bundle quand l'application tourne sur Symfony ; dans un autre code PHP, ou à l'intérieur d'un modèle de
domaine, utilisez directement `phil/geo`. Le mappage Doctrine a besoin de l'ORM et se désactive avec `doctrine_mapping`.
Le bundle n'ajoute aucune géographie qui lui soit propre : chaque pièce enveloppe un type de la bibliothèque, et le
navigateur calcule la même distance orthodromique que `Coordinates::distanceTo()`.

## État

| Partie | État |
|---|---|
| `Coordinates` comme objet intégrable Doctrine, facultatif | Implémenté et testé |
| `CoordinatesType` : une position en deux champs | Implémenté et testé |
| Twig : `geo_distance()`, `format_distance`, `geo_attributes()`, `geo_schema()` | Implémenté et testé |
| Le contrôleur Stimulus `distance-sort` | Implémenté et testé |
| Les géocodeurs de la configuration (en chaîne, mis en cache) et le localisateur IP, MaxMind ou ipstack | Implémenté et testé |
| `phil:geo:maxmind:update` : la plus récente base MaxMind, vérifiée et mise en place | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, l'enregistrement, la configuration, le contrôleur Stimulus. |
| [Doctrine](doc/fr/doctrine.md) | Intégrer une position, chercher près d'un point. |
| [Formulaires](doc/fr/forms.md) | `CoordinatesType`. |
| [Twig](doc/fr/twig.md) | `geo_distance()`, `format_distance`, `geo_attributes()`, `geo_schema()`. |
| [Trier par distance](doc/fr/distance-sort.md) | Le contrôleur Stimulus, et pourquoi la position reste dans le navigateur. |
| [Géocodage](doc/fr/geocoding.md) | Les fournisseurs de la configuration, la chaîne, le cache, le localisateur IP. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | La façon dont le bundle est testé et comment exécuter ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/geo-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\GeoBundle\PhilGeoBundle::class => ['all' => true],
];
```

```twig
<div data-controller="phil--geo-bundle--distance-sort" data-phil--geo-bundle--distance-sort-locate-value="true">
    {% for store in stores %}
        <article {{ geo_attributes(store.position, 3) }}>{{ store.name }} <span data-distance></span></article>
    {% endfor %}
</div>
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl`, et `ext-zlib` pour `phil:geo:maxmind:update` ; `phil/geo` 0.1 ;
`symfony/config`, `symfony/dependency-injection`, `symfony/form` et `symfony/options-resolver` 8.2 ou plus récent.
Doctrine ORM et Twig sont facultatifs ; le contrôleur a besoin de `@hotwired/stimulus` 3, que fournit l'application.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
