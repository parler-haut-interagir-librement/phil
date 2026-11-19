<!-- phil-dev source-sha256=ec1ebd2273ff5f7eb462801150bbaaa41bc0caecd9e41e1a335d38df06365ddd -->
# Événements

Quand on lui donne un répartiteur d'événements PSR-14, le moteur répartit un événement par changement, une fois que le
magasin l'a. Chaque événement nomme ce qui a changé par ses seuls identifiants — campagne, proposition, évaluateur,
numéro de vote — ; un écouteur lit le reste dans les magasins. Aucun ne porte une note, un nom ou une adresse : un
événement peut donc partir tel quel vers une file de messages.

| Événement | Réparti par | Quand |
|---|---|---|
| `PhaseChanged` | `ReviewBoard::advance()` | La campagne est dans une autre phase que la dernière enregistrée ; il porte les deux phases. |
| `ReviewSubmitted` | `ReviewBoard::review()` | Un évaluateur a noté une proposition, ou l'a notée de nouveau. |
| `DecisionRecorded` | `ReviewBoard::decide()` | Une décision en attente a été consignée ou remplacée. |
| `DecisionsApplied` | `ReviewBoard::applyDecisions()` | Des décisions en attente ont été appliquées ; il liste leurs propositions. Non réparti quand aucune n'était en attente. |
| `PairwiseVoteCast` | `PairwiseVoting::vote()` | Un vote a été ajouté ; il porte son numéro. |

Les phases suivent l'horloge, que quelqu'un appelle `advance()` ou non : `PhaseChanged` est la façon dont une
application l'apprend — pour ouvrir les écrans d'évaluation, relancer les évaluateurs, clore l'appel.
Appelez `advance()` depuis une tâche planifiée assez souvent pour la précision que vous voulez ; elle ne fait rien tant
que la phase reste la même.

```php
use Phil\ProposalReview\Event\DecisionsApplied;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class NotifyAuthors
{
    public function __invoke(DecisionsApplied $event): void
    {
        foreach ($event->proposals as $proposal) {
            // read the decision and the authors, and send each author their message
        }
    }
}
```
