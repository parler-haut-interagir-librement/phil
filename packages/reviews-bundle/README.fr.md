<!-- phil-dev source-sha256=980128fd0a4661fa58a375878ee10da292626c03f1230538d1f36a7e3863bbfc -->
# phil/reviews-bundle

**Avis et notes modérés sur un produit, un jeu ou un lieu, avec avis multicritères et votes d'utilité dès la première
version.**

phil/reviews-bundle est un bundle Symfony. Un membre donne à un sujet une note globale, les notes des critères que
déclare son type, un texte et jusqu'à trois superlatifs ; les autres votent sur l'utilité de l'avis ; le marchand ou
l'organisateur répond publiquement. Les notes de chaque sujet — une moyenne pondérée publiée avec ses paramètres, la
moyenne de chaque critère, les superlatifs donnés le plus souvent — sont conservées à côté des avis et recalculées
après chaque changement.

- Un avis par membre et par sujet, et un vote par membre et par avis, décidés par les contraintes d'unicité des
  tables.
- Des avis réservés aux participants dans une fenêtre, 30 jours par défaut, et la mention « achat vérifié » ou « a
  participé », par un port que le commerce ou la billetterie implémente.
- Chaque avis est publié, les négatifs comme les positifs : un avis n'est retiré que sous un critère publié, et son
  auteur conteste le retrait une fois, avec ses preuves.
- Un avis donné contre une compensation le dit toujours.
- Les avis d'un sujet sont suspendus et repris sans perdre ceux qui ont été donnés.
- Signalés par `phil/moderation-bundle`, exportés et effacés par `phil/privacy-bundle`, donnés comme un
  `AggregateRating` de schema.org par `phil/structured-data` quand chaque avis a été recueilli sur le site, le
  formulaire soumis à `phil/spam-protection-bundle` et à `phil/rate-limit-bundle` quand ils sont installés.
- Des pages dessinées avec les recettes du kit PHIL UX : des étoiles lues comme du texte par les lecteurs d'écran, des
  formulaires qui fonctionnent sans JavaScript, et un contrôleur Stimulus qui envoie les votes en arrière-plan.

## État

| Partie | État |
|---|---|
| Avis, votes, réponses, retraits et contestations (`Reviews`) | Implémenté et testé |
| Notes, moyenne pondérée, classement et `phil:reviews:recompute` (`Ratings`) | Implémenté et testé |
| Dépôts DBAL et leur écouteur de schéma | Implémenté et testé |
| Modération, vie privée, données structurées, protection contre le pourriel et limites de débit | Implémenté et testé |
| Pages, fonctions Twig et contrôleur Stimulus des votes | Implémenté et testé |
| Recette Flex (`recipe/0.1`) | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les tables et les pages. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Avis](doc/fr/reviews.md) | Écrire et modifier un avis, les votes, les réponses, les participants, les compensations, la suspension. |
| [Notes](doc/fr/ratings.md) | La moyenne pondérée et ses paramètres, les critères, les superlatifs, le classement, `AggregateRating`. |
| [Modération](doc/fr/moderation.md) | Les critères publiés, les signalements, les retraits, les contestations, la protection contre le pourriel et les limites de débit. |
| [Pages](doc/fr/pages.md) | Les routes, les gabarits, les recettes du kit, l'accessibilité et le contrôleur Stimulus. |
| [Vie privée](doc/fr/privacy.md) | L'export et l'effacement des avis, des votes et des réponses d'un membre. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports à implémenter, les services à remplacer, les événements, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\ReviewsBundle\PhilReviewsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_reviews.yaml
phil_reviews:
    types:
        game:
            criteria: [rules, replayability]
            superlatives: [great-for-beginners, gorgeous, quick-to-teach]

# config/routes/phil_reviews.yaml
phil_reviews:
    resource: '@PhilReviewsBundle/config/routes.php'
    prefix: /reviews
```

Les avis du jeu `catan` sont alors à `/reviews/game/catan`.

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente et Doctrine DBAL 4.5 ou une version plus
récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
