<!-- phil-dev source-sha256=4e1442dbc383c6cd19a9e99d0ccfc8c5a7984e88b13382c1c72a6cc823831dc1 -->
# Installation

```sh
composer require phil/social-cards-bundle
```

## Prérequis

| Paquet | Version | Sert à |
|---|---|---|
| PHP | 8.5 ou plus récent, avec GD et FreeType | Les cartes |
| `phil/social-cards` | ^0.1 | Les métadonnées, les balises, les cartes, le lecteur |
| `symfony/http-kernel`, `symfony/http-foundation`, `symfony/routing` | ^8.2 | La requête de la page, la route de la carte |
| `phil/safe-http` | ^0.1 | Le client protégé des aperçus |
| `symfony/cache-contracts` | ^3.6 | Le cache des aperçus |
| `twig/twig` | ^3.21 | `social_meta()` et `social_page()` |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration et les services |
| `symfony/messenger` | ^8.2, facultatif | Les cartes dessinées par un worker |
| `symfony/console` | ^8.2, facultatif | `phil:social-cards:check` |
| `symfony/ux-twig-component` | ^3.7, facultatif | Le composant `LinkPreview` |
| `symfony/rate-limiter` | ^8.2, facultatif | La limite de débit des liens collés |
| `symfony/stimulus-bundle` | ^3.7, facultatif | Le contrôleur `link-preview`, depuis `assets/` |
| `phil/media-bundle` | ^0.1, facultatif | Un média comme image d'une page |
| `phil/http-cache-tags-bundle` | ^0.1, facultatif | Les étiquettes des cartes servies |

## Enregistrer

```php
// config/bundles.php
return [
    // ...
    Phil\SocialCardsBundle\PhilSocialCardsBundle::class => ['all' => true],
];
```

## La route

```yaml
# config/routes/phil_social_cards.yaml
phil_social_cards:
    resource: '@PhilSocialCardsBundle/config/routes.php'
```

| Route | Chemin |
|---|---|
| `phil_social_cards_image` | `/social-cards/{fingerprint}.png`, `GET` et `HEAD`, l'empreinte en 32 chiffres hexadécimaux |

Le point de terminaison des liens collés est importé à part, derrière le pare-feu des membres qui écrivent :

```yaml
# config/routes/phil_social_cards.yaml
phil_social_cards_link_preview:
    resource: '@PhilSocialCardsBundle/config/link_preview_routes.php'
```

| Route | Chemin |
|---|---|
| `phil_social_cards_link_preview` | `/social-cards/link-preview?url=…`, `GET` |

## Le contrôleur Stimulus

Le contrôleur `link-preview` est dans `assets/`, déclaré dans son `package.json`, pour Symfony UX : avec AssetMapper,
`importmap:require` ou le `controllers.json` du StimulusBundle l'active sous le nom
`phil--social-cards-bundle--link-preview`. Il a besoin de `@hotwired/stimulus` et de rien d'autre.
