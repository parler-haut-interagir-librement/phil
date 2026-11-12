<!-- phil-dev source-sha256=bc50b0e6ab7ae2782919f49d2c2ba903d25fae646cd6f35e30f36f50f966ca02 -->
# phil/directory-bundle

**Un répertoire de fiches, comme des balados ou des boutiques, que les membres rédigent et que les administrateurs
publient.**

phil/directory-bundle conserve des fiches classées par genre, comme des balados, des boutiques locales ou des terrains
de GN, dans une seule table. Ajouter un genre demande une ligne de configuration et aucune classe. Chaque fiche est un
contenu de phil/content-bundle : chaque modification est donc une version, et l'historique est conservé, page par page.
En plus du titre, de la description, du *slug*, du corps et du référencement de content-bundle, une fiche porte une
fiche de contact, une adresse, une position sur la carte, les fiches qu'elle nomme et l'auteur de chaque version. Le
bundle donne à chaque genre ses routes, ses formulaires et ses pages, ainsi qu'une section du plan du site. Son auteur
décide qui trouve une fiche, comme le nomme phil/indexing-consent : tout le monde, les visiteurs du site mais pas les
moteurs de recherche, ou seulement qui a son adresse.

Il s'adresse à un site communautaire où les gens inscrivent ce qu'ils connaissent. Toute personne connectée rédige une
fiche et en devient l'auteur. L'auteur, les rédacteurs que l'auteur nomme et les administrateurs la modifient, et un
administrateur la publie, sauf si `publish_every_change` publie chaque modification sur-le-champ. Les genres sont liés
comme le dit la configuration (un GN se joue sur des terrains de GN), et une fiche montre à la fois les fiches qu'elle
nomme et celles qui la nomment. Pour des pages que seuls des rédacteurs écrivent, phil/content-bundle suffit à lui seul.

C'est un bundle Symfony construit sur phil/content-bundle, avec Doctrine, Symfony Form et un *voter* de Symfony Security
pour les attributs `phil_directory.edit`, `phil_directory.editors` et `phil_directory.publish`. Il ajoute ses propres
capacités au pipeline de content-bundle, et tire les fiches de contact, les adresses et les coordonnées de
phil/contact-bundle, phil/address-bundle et phil/geo-bundle.

## État

| Partie | État |
|---|---|
| `Listing` et `ListingDimension` : une table, des genres, des versions | Implémenté et testé |
| Les capacités contact, adresse, position, liens et auteur de la version | Implémenté et testé |
| `Directory` : créer, modifier, publier, rédacteurs, historique | Implémenté et testé |
| Les relations entre genres, déclarées dans la configuration, montrées sur les deux fiches | Implémenté et testé |
| Les formulaires, le contrôleur, les routes de chaque genre | Implémenté et testé |
| Le *voter* `phil_directory.edit`, `.editors`, `.publish` | Implémenté et testé |
| Le plan du site : une section par genre | Implémenté et testé |
| Qui trouve une fiche : listée, gardée hors des moteurs de recherche, non listée (phil/indexing-consent) | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, l'enregistrement, la sécurité que lit le bundle. |
| [Configuration](doc/fr/configuration.md) | Les genres, le rôle d'administrateur, la publication, les mots des adresses. |
| [Fiches](doc/fr/listings.md) | La rédaction, la modification, les rédacteurs, la publication, l'historique, qui peut faire quoi, qui trouve une fiche, et l'effacement d'un utilisateur. |
| [Routes et pages](doc/fr/routes.md) | Les routes de chaque genre, les gabarits, les traductions, le plan du site. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | La façon dont le bundle est testé, et comment exécuter ses tests. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/directory-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\DirectoryBundle\PhilDirectoryBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_directory.yaml
phil_directory:
    genres:
        podcast: { path: podcasts, title: Podcasts }
```

```yaml
# config/routes/phil_directory.yaml
phil_directory:
    resource: .
    type: phil_directory
```

## Prérequis

PHP 8.5 ou plus récent ; `phil/content-bundle`, `phil/contact-bundle`, `phil/address-bundle`, `phil/geo-bundle` et
`phil/ddd-bundle` 0.1 ; `phil/indexing-consent` 0.1 ; Doctrine ORM 3.7 ; Symfony 8.2 (form, routing, security-core) ;
Twig 3.21. `phil/sitemap` énumère les pages lorsque `phil/sitemap-bundle` est installé.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
