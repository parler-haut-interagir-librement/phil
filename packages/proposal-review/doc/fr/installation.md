<!-- phil-dev source-sha256=05b087d34015ceb634b1a3d6b7412cfd68ea302bb06354a1218e5abe65cc7dec -->
# Installation

```sh
composer require phil/proposal-review
```

Le paquet exige PHP 8.5 ou une version plus récente, `psr/clock` et `psr/event-dispatcher` ; `symfony/polyfill-php86`
fournit `\SortDirection` avant PHP 8.6. Il exige une horloge — `symfony/clock` dans une application Symfony — et prend
un répartiteur d'événements quand on lui en donne un.

Les services ne gardent aucun état entre les appels. Dans une application Symfony, déclarez les magasins que votre
application implémente (voir [Stockage](storage.md)) et les deux services :

```yaml
# config/services.yaml
services:
    Phil\ProposalReview\Contract\CampaignStore: '@App\Review\DoctrineCampaignStore'
    Phil\ProposalReview\Contract\ProposalStore: '@App\Review\DoctrineProposalStore'
    Phil\ProposalReview\Contract\ReviewStore: '@App\Review\DoctrineReviewStore'
    Phil\ProposalReview\Contract\VoteLog: '@App\Review\DoctrineVoteLog'

    Phil\ProposalReview\ReviewBoard:
        arguments:
            $events: '@event_dispatcher'

    Phil\ProposalReview\Pairwise\PairwiseVoting:
        arguments:
            $events: '@event_dispatcher'
```

L'horloge est câblée automatiquement depuis `Psr\Clock\ClockInterface`, que Symfony associe à sa propre horloge. Les
magasins en mémoire sous `Memory\` servent aux tests et aux démonstrations.
