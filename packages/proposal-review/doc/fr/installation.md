<!-- phil-dev source-sha256=f36cacb3cd6b3128bd897b2046c75cb58ec8707abd7400dd2219c1bee089f5ca -->
# Installation

```sh
composer require phil/proposal-review
```

Le paquet exige PHP 8.5 ou une version plus récente, `psr/clock` et `psr/event-dispatcher` ; `symfony/polyfill-php86`
fournit `\SortDirection` avant PHP 8.6. Il exige une horloge — `symfony/clock` dans une application Symfony — et prend
un répartiteur d'événements quand on lui en donne un.

| Paquet | Contrainte | Pour |
|---|---|---|
| `doctrine/dbal` | `^4.5`, suggéré | Les magasins sous `Dbal\`, dans une base de données. |

Les services ne gardent aucun état entre les appels. Dans une application Symfony, déclarez les magasins — ceux sous
`Dbal\` sur la connexion DBAL de l'application, ou ceux de l'application (voir [Stockage](storage.md)) — et les deux
services :

```yaml
# config/services.yaml
services:
    Phil\ProposalReview\Dbal\DbalCampaignStore: ~
    Phil\ProposalReview\Dbal\DbalProposalStore: ~
    Phil\ProposalReview\Dbal\DbalReviewStore: ~
    Phil\ProposalReview\Dbal\DbalVoteLog: ~
    Phil\ProposalReview\Contract\CampaignStore: '@Phil\ProposalReview\Dbal\DbalCampaignStore'
    Phil\ProposalReview\Contract\ProposalStore: '@Phil\ProposalReview\Dbal\DbalProposalStore'
    Phil\ProposalReview\Contract\ReviewStore: '@Phil\ProposalReview\Dbal\DbalReviewStore'
    Phil\ProposalReview\Contract\VoteLog: '@Phil\ProposalReview\Dbal\DbalVoteLog'

    Phil\ProposalReview\ReviewBoard:
        arguments:
            $events: '@event_dispatcher'

    Phil\ProposalReview\Pairwise\PairwiseVoting:
        arguments:
            $events: '@event_dispatcher'
```

L'horloge est câblée automatiquement depuis `Psr\Clock\ClockInterface`, que Symfony associe à sa propre horloge, et les
magasins DBAL depuis `Doctrine\DBAL\Connection`. Chaque magasin DBAL prend un préfixe de table comme second argument,
`phil_review_` par défaut.
