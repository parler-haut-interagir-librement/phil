<!-- phil-dev source-sha256=a3e4b238610b2c87ab64c45ddb88ad6d8947fae61e6d2a394052d0d3bd3253bd -->
# Installation

```sh
composer require phil/reviews-bundle
```

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_reviews.yaml` avec un type de sujet et
importe les pages sous `/reviews`. Sans elle, enregistrez le bundle dans `config/bundles.php` :

```php
Phil\ReviewsBundle\PhilReviewsBundle::class => ['all' => true],
```

déclarez au moins un type de sujet ([Configuration](configuration.md)), et importez les pages :

```yaml
# config/routes/phil_reviews.yaml
phil_reviews:
    resource: '@PhilReviewsBundle/config/routes.php'
    prefix: /reviews
```

## Les tables

Le bundle conserve ses avis par Doctrine DBAL, sur la connexion que nomme `connection`. Avec l'ORM installé, ses tables
sont ajoutées au schéma que l'ORM génère pour cette connexion, de sorte que les migrations les écrivent :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

| Table | Ce qu'elle contient |
|---|---|
| `phil_review` | Une ligne par avis, unique par sujet et par auteur. |
| `phil_review_score` | La note de chaque critère qu'un avis a noté. |
| `phil_review_superlative` | Les superlatifs qu'un avis a donnés. |
| `phil_review_vote` | Les votes d'utilité, uniques par avis et par votant. |
| `phil_review_subject` | Les notes de chaque sujet, et si ses avis sont suspendus. |

## Les pages

Les pages ont besoin de Twig, du composant Routing de Symfony et de la protection CSRF de Symfony, et dessinent les
recettes `Rating`, `Button`, `Badge`, `Input`, `Checkbox` et `Textarea` du kit PHIL UX, installées avec le Toolkit de
Symfony UX :

```sh
composer require symfony/twig-bundle symfony/ux-twig-component phil/ux-kit
php bin/console ux:install rating button badge input checkbox textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Avec AssetMapper, le contrôleur Stimulus des votes est servi sans étape de construction.

## Paquets facultatifs

| Paquet | Ce qu'il ajoute |
|---|---|
| `phil/moderation-bundle` | Les signalements dans la file unique de la modération, et les retraits sous les critères publiés. |
| `phil/privacy-bundle` | Les avis, les votes et les réponses dans l'export et l'effacement des données d'un membre. |
| `phil/structured-data` | Le `AggregateRating` de schema.org d'un sujet. |
| `phil/spam-protection-bundle` | Le pot de miel, le jeton à usage unique, la preuve de travail et les règles de contenu du formulaire d'avis. |
| `phil/rate-limit-bundle` | Les limites des formulaires, par les règles que nomme `rate_limit`. |
| `symfony/security-bundle` | Le membre courant et ceux qui répondent, à partir de l'utilisateur connecté. |
| `symfony/console` | `phil:reviews:recompute`. |

Le paquet exige PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente et Doctrine DBAL 4.5 ou
une version plus récente.
