<!-- phil-dev source-sha256=c7f5e8b122bb59282b5279e0b1dde034131409d774e36cae6f4f2947b2e00777 -->
# Installation

## Prérequis

| Paquet | Version | Sert à |
|---|---|---|
| PHP | 8.5 ou plus récent, avec mbstring et XMLWriter | Les vérifications des entrées, les écrivains Atom et RSS |
| `phil/url` | ^0.1 | Chaque adresse : les pages, les entrées, les concentrateurs |
| `phil/publishing` | ^0.1 | `PagePublished` et `PageRetired`, qui annoncent un flux |
| `phil/safe-http` | ^0.2 | Les requêtes aux concentrateurs |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` | ^8.2 | Les flux servis |
| `symfony/messenger` | ^8.2 | Les annonces aux concentrateurs |
| `twig/twig` | ^3.21 | `phil_feed_links()` |
| `psr/log` | ^3.0 | Les refus et les échecs des concentrateurs |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration et les services |
| `phil/content-bundle` | ^0.3, facultatif | Les flux de contenus |

## Enregistrement

```php
// config/bundles.php
return [
    // ...
    Phil\FeedBundle\PhilFeedBundle::class => ['all' => true],
];
```

La fonction Twig a besoin de TwigBundle ; les annonces ont besoin de Messenger de FrameworkBundle. Sans concentrateur
configuré, Messenger n'est pas utilisé.

## Routes

```yaml
# config/routes/phil_feed.yaml
phil_feed:
    resource: '@PhilFeedBundle/config/routes.php'
```

| Route | Chemin | Méthodes |
|---|---|---|
| `phil_feed` | `/feeds/{name}.{format}`, `format` valant `atom`, `rss` ou `json` | `GET`, `HEAD` |

Un `prefix` sur l'importation déplace les flux, comme `/fr/feeds/…` ; l'adresse que nomme chaque flux, les liens d'une
page et les sujets annoncés aux concentrateurs suivent la route.

## La recette Flex

La recette de `recipe/0.1` enregistre le bundle, ajoute `config/packages/phil_feed.yaml` avec les valeurs par défaut en
commentaire et `config/routes/phil_feed.yaml` avec les routes.
