<!-- phil-dev source-sha256=87a89a173708f96bf9d914d31e28d3d7f83c54e8cef70507439d7e6708575682 -->
# Points d'extension

## `PollPolicy`

Le port qui décide qui peut faire quoi, dans `Phil\PollBundle\Application\Contract`. L'application l'implémente avec
ses propres rôles, adhésions ou invitations et nomme le service sous `phil_poll.policy`.

```php
interface PollPolicy
{
    public function mayCreate(ResourceReference $actor, ?ResourceReference $subject): bool;

    public function mayVote(Poll $poll, ResourceReference $voter): bool;

    public function mayManage(Poll $poll, ?ResourceReference $actor): bool;
}
```

- `create()` consulte `mayCreate()`, avec le sujet du brouillon.
- `cast()`, `change()`, `changeWithReceipt()` et `withdraw()` consultent `mayVote()`, une fois qu'on sait le sondage
  ouvert.
- `close()`, `reschedule()` et `delete()` consultent `mayManage()`, tout comme la visibilité des résultats : quiconque
  gère un sondage en voit les résultats. Son acteur est `null` pour quelqu'un qui n'est pas connecté.

`DefaultPollPolicy` laisse tout le monde créer et voter, et seul le créateur gérer. Une politique réservée aux
membres :

```php
use Phil\PollBundle\Application\Contract\PollPolicy;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\Resource\ResourceReference;

final readonly class MembersPolicy implements PollPolicy
{
    public function __construct(
        private Members $members,
    ) {
    }

    public function mayCreate(ResourceReference $actor, ?ResourceReference $subject): bool
    {
        return $this->members->isBoardMember($actor);
    }

    public function mayVote(Poll $poll, ResourceReference $voter): bool
    {
        return $this->members->isMemberInGoodStanding($voter);
    }

    public function mayManage(Poll $poll, ?ResourceReference $actor): bool
    {
        return null !== $actor && ($poll->isCreatedBy($actor) || $this->members->isBoardMember($actor));
    }
}
```

La politique est consultée à chaque action : elle ne garde aucun état entre les requêtes, ou le réinitialise avec
`kernel.reset`.

## Méthodes de dépouillement

Chaque service qui implémente `Phil\Poll\Contract\CountingMethod` est ajouté à `CountingMethods` à côté des six de
la bibliothèque, par autoconfiguration ; un sondage la nomme par son `name()`. Voir les points d'extension de
`phil/poll` pour le contrat. Retirer une méthode que nomme un sondage conservé fait échouer ses résultats avec
`UnknownCountingMethod`.

Une méthode qui pourvoit plusieurs sièges implémente `Phil\PollBundle\Domain\Counting\Contract\ElectingMethod`, qui
étend `CountingMethod` : `elect($rules, $ballots, $seats)` renvoie une `Election` avec ses tours, `count()` pourvoyant
un siège. `Polls` laisse un sondage dépouillé par elle nommer plus d'un siège, donne son élection par `election()` et
son résultat par `results()`. Les scores d'une élection sont des milliardièmes de vote.

## Les stockages

`PollRepository` et `VoteRepository`, dans `Phil\PollBundle\Domain\Repository`, sont les ports de l'endroit où vivent
les sondages et les bulletins ; le bundle est fourni avec des stockages Doctrine et en mémoire. Un autre stockage
conserve un nouveau bulletin et sa participation ensemble ou pas du tout, refuse un deuxième bulletin d'un votant avec
`AlreadyVoted`, et lit les bulletins d'un dépouillement au fil de l'eau plutôt que de les charger d'un coup.

Un autre stockage de sondages implémente `raiseRevisionWhileOpen($id, $at, $write)` comme une seule étape atomique : il
vérifie que le sondage est ouvert à `$at` sur le sondage tel qu'il est maintenant dans le stockage — non sur un objet
lu plus tôt —, exécute `$write`, puis augmente la révision, le tout ou rien, et empêche une fermeture de valider entre
la vérification et l'écriture. Un `UPDATE` conditionnel dont on compte les lignes touchées, ou un verrou sur la ligne du
sondage, y arrive ; renvoyer `false` sans appeler `$write` est le refus.

### Mise à niveau d'un stockage depuis la version 0.2

`raiseRevisionWhileOpen()` est nouvelle dans `PollRepository` en 0.3 : un stockage de sondages écrit pour la version 0.2
l'implémente avant de tourner avec la version 0.3. `Polls` n'appelle plus `raiseRevision()` pour un bulletin, seulement
pour une fermeture et un nouvel horaire. Les stockages du bundle n'ont besoin de rien : le stockage Doctrine utilise les
colonnes que la table a déjà, sans migration.

## Les gabarits

`@PhilPoll/ballot.html.twig` et `@PhilPoll/results.html.twig` ont des blocs qu'une page qui les intègre remplace :
`choices`, `status` et `actions` pour le bulletin ; `lines` et `revealed` pour les résultats. Un bloc dessine les
recettes du kit par la table `kit` que définit le gabarit — `kit.Checkbox`, `kit.Field`, `kit.Progress` — ou par
`phil_poll_kit('Name')`, qui préfixe le sous-répertoire des recettes. Une application remplace un gabarit entier sous
`templates/bundles/PhilPollBundle/`, et les mots sous son propre domaine de traduction `phil_poll`. Une page qui
dessine les vues à sa façon les lit dans `PollViews`. Voir [L'interface de vote](voting-interface.md).

| Quoi | Où |
|---|---|
| Les recettes | Installées par le Symfony UX Toolkit depuis le kit PHIL UX ou le kit Shadcn ; leur sous-répertoire lu dans `ux_toolkit`, ou `kit_subdirectory` |

## Les événements

Les événements décrits dans [Événements](events.md) servent à agir après un vote — rafraîchir un cache, prévenir le
créateur, faire passer une idée à l'état suivant — sans modifier le bundle ; l'événement du DOM `poll:revealed` sert à
agir dans le navigateur quand les résultats sont révélés.
