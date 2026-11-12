<!-- phil-dev source-sha256=cb61addb390a895df8613b570da683106ccd1f7c008915fbaf2d3e42920cb64f -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `phil/content-bundle` | ^0.1@dev | Les fiches sont des contenus : versions, flux de travail, routes |
| `phil/contact-bundle`, `phil/address-bundle`, `phil/geo-bundle` | ^0.1@dev | La fiche de contact, l'adresse et la position d'une fiche, leurs colonnes et leurs types de formulaire |
| `phil/ddd-bundle` | ^0.1@dev | L'identifiant d'une fiche et son type Doctrine |
| `phil/sitemap` | ^0.1@dev | Le fournisseur du plan du site, recueilli lorsque `phil/sitemap-bundle` est installé |
| `phil/indexing-consent` | ^0.1@dev | Qui trouve une fiche, comme son auteur en décide |
| `doctrine/orm` | ^3.7 | Les deux tables |
| `symfony/form`, `symfony/routing`, `symfony/http-kernel` | ^8.2 | Les formulaires, les routes et le contrôleur |
| `symfony/security-core` | ^8.2 | La personne connectée, et le *voter* |
| `twig/twig` | ^3.21 | Les pages |

```sh
composer require phil/directory-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ContentBundle\PhilContentBundle::class => ['all' => true],
    Phil\ContactBundle\PhilContactBundle::class => ['all' => true],
    Phil\AddressBundle\PhilAddressBundle::class => ['all' => true],
    Phil\GeoBundle\PhilGeoBundle::class => ['all' => true],
    Phil\DirectoryBundle\PhilDirectoryBundle::class => ['all' => true],
];
```

## Ce que le bundle ajoute au préalable

Lorsque l'extension est enregistrée, le bundle ajoute au préalable :

- à `doctrine` : le type `phil_directory_listing_id` et l'association par attributs `PhilDirectory` de
  `src/Domain/Model` ;
- à `phil_content` : `Listing` comme racine de contenu, sous la clé de ressource `listing`.

L'application crée les tables avec ses propres migrations. La table des fiches garde qui trouve une fiche dans deux
colonnes, `indexing` (`listed` par défaut) et `site_listed` (vrai par défaut), pour qu'une migration les ajoute aux
lignes existantes sans changer ce qu'elles montrent.

## Sécurité

Le bundle lit les services `security.token_storage` et `security.authorization_checker`, que fournit SecurityBundle.
L'utilisateur est l'identifiant du jeton — `getUserIdentifier()` —, et un administrateur est un utilisateur à qui le
rôle `admin_role` est accordé, par la hiérarchie des rôles lorsqu'il y en a une. Une personne non connectée lit les
fiches publiées, et rien d'autre.
