<!-- phil-dev source-sha256=8ae74c8850cce46a92cb93f2516829a63dadb2cdb660452b208e344c2c410a89 -->
# Installation

```sh
composer require phil/audience-segments
```

Le paquet exige PHP 8.5 ou une version plus récente, phil/rules, psr/clock et psr/event-dispatcher. Il ne fournit aucun
bundle Symfony : l'application implémente les ports au-dessus de ses propres modules et enregistre les services.

## Branchement

```yaml
# config/services.yaml
services:
    app.audience_conditions:
        class: Phil\Rules\Type\ConditionTypes
        factory: ['Phil\AudienceSegments\AudienceConditions', 'types']
        arguments:
            $participation: '@App\Audience\EventParticipation'
            $purchases: '@App\Audience\ShopPurchases'
            $tags: '@App\Audience\PeopleTags'

    Phil\AudienceSegments\SegmentJson:
        arguments:
            $types: '@app.audience_conditions'

    Phil\AudienceSegments\Segments:
        arguments:
            $audience: '@App\Audience\NewsletterSubscribers'
            $exclusions: '@App\Audience\Suppressions'

    Phil\AudienceSegments\Recalculation:
        arguments:
            $store: '@App\Audience\DoctrineMembershipStore'
```

Un type de condition n'est enregistré que si son port est fourni : une application sans boutique omet `$purchases`, et
un segment qui nomme `purchase.made` est alors refusé à sa lecture. L'horloge et le répartiteur d'événements de Symfony
remplissent les interfaces PSR.

## Stocker les segments

Une application conserve chaque segment sous la forme de son identifiant et de sa définition, le texte que donne
`SegmentJson::write()`, et le relit avec `SegmentJson::read($id, $definition)`. Une liste figée est conservée sous la
forme de son segment, de son nom, de son empreinte, de sa date et de ses membres, une ligne par membre ou une colonne
d'identifiants, et se reconstruit avec `new FrozenList(...)`, qui vérifie que les membres sont distincts et triés.

## Recalculer selon un horaire

`Recalculation::recalculate()` parcourt toute l'audience ; lancez-le depuis une commande ou un message planifié plutôt
que dans une requête. Avec le Scheduler de Symfony, un message récurrent par segment, portant le seul identifiant du
segment, suffit.
