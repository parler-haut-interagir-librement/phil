<!-- phil-dev source-sha256=2da746f565a4d3777b8ec0eb60cc22b6173843379822a86db95424b19715bf96 -->
# Demandes

## Une demande est une conversation

`Helpdesk` est le point d'entrée. Une demande ouvre une conversation de phil/conversation-bundle avec son seul
demandeur, par `Conversations::startOpen()`, et garde à côté d'elle une file, un statut et un responsable :

```php
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;

$ticket = $helpdesk->open(ParticipantId::fromString('member-7'), QueueName::fromString('billing'), 'Wrong invoice', 'I was charged twice.');
```

La demande est nommée par l'identifiant de sa conversation, `$ticket->conversation`. Son sujet et ses messages sont ceux
de la conversation : scellés au repos, lus et non lus, notifiés à ses participants. Une file non déclarée lève
`InvalidQueue` ; un sujet ou un corps que les conversations refusent lève leur `InvalidConversation`.

## Le demandeur

```php
$helpdesk->requests($requester);                    // their requests, latest change first, by pages of 50
$helpdesk->ofRequester($id, $requester);            // one of them; someone else's is not found
$helpdesk->answer($id, $requester, 'Any news?');
$helpdesk->withdraw($id, $requester);               // closes it
```

## Les agents

Un agent travaille les files que `QueueAccess` accorde à la personne connectée :

```php
$helpdesk->queues();                                // the queues they work
$helpdesk->desk();                                  // the requests of those queues, latest change first
$helpdesk->desk(QueueName::fromString('billing'), Status::Waiting, $agent, page: 2);
$ticket = $helpdesk->forAgent($id);                 // a request of another queue is not found

$helpdesk->reply($id, $agent, 'We are on it.');     // joins the conversation, assigns it to them if it was to nobody
$helpdesk->replyWith($id, $agent, 'thanks', 'fr');  // a canned reply, written for the request
$helpdesk->assign($id, $colleague, $agent);         // the colleague joins the conversation; null unassigns
$helpdesk->move($id, QueueName::fromString('tech')); // to another queue they work
$helpdesk->apply($id, 'resolve');                   // take, resolve, reopen or close
$helpdesk->transitions($ticket);                    // those they may apply now
```

Un agent qui répond ou à qui une demande est assignée rejoint la conversation : il reçoit ses prochains messages, et le
demandeur le voit comme un participant. Une demande d'une file que l'agent ne travaille pas lève `TicketNotFound`,
comme une demande qui n'existe pas.

## Le workflow

Le statut passe par la machine à états de Symfony Workflow `phil_helpdesk_ticket` :

| Transition | De | Vers | Appliquée |
|---|---|---|---|
| `take` | nouvelle | ouverte | quand la demande est assignée, ou par un agent |
| `wait` | nouvelle, ouverte | en attente | quand un agent répond |
| `answer` | en attente, résolue | ouverte | quand le demandeur répond |
| `resolve` | nouvelle, ouverte, en attente | résolue | par un agent |
| `reopen` | résolue | ouverte | par un agent |
| `close` | nouvelle, ouverte, en attente, résolue | fermée | par un agent, ou le demandeur |

Une demande fermée ne prend plus de messages : une réponse, du demandeur ou d'un agent, lève `TransitionRefused`, comme une transition que le
workflow n'autorise pas depuis l'état où se trouve la demande. Le workflow émet les événements du composant par le
répartiteur d'événements de l'application : une garde retient une transition et un écouteur la suit :

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsEventListener('workflow.phil_helpdesk_ticket.guard.close')]
final class CloseAfterTheSurvey
{
    public function __invoke(GuardEvent $event): void
    {
        // $event->getSubject() is the Ticket; $event->setBlocked(true, 'Ask the survey first.') holds the transition.
    }
}
```

## Événements

`TicketOpened` suit chaque demande ouverte, `TicketAssigned` chaque assignation, les deux par identifiants seulement ;
les messages d'une demande sont les `MessageSent` de phil/conversation-bundle, et ses changements de statut les
événements du workflow.

## Les pages

Les routes des demandeurs, de `config/routes.php` :

| Route | Chemin | Ce qu'elle montre |
|---|---|---|
| `phil_helpdesk_requests` | `/` | Les demandes du demandeur et leur statut |
| `phil_helpdesk_new` | `/new` | Le formulaire qui ouvre une demande : file, sujet, question |
| `phil_helpdesk_request` | `/{id}` | Une demande : sa file, son statut et sa conversation, les formulaires qui y répondent et la ferment |
| `phil_helpdesk_answer`, `phil_helpdesk_withdraw` | `/{id}/answer`, `/{id}/withdraw` | Soumises par ces formulaires |

Les routes des agents, de `config/routes_agents.php`, et greffées sur les tableaux de bord d'EasyAdmin :

| Route | Chemin | Ce qu'elle montre |
|---|---|---|
| `phil_helpdesk_desk` | `/` | Le bureau : les demandes des files de l'agent, filtrées par file, statut et « assignées à moi » |
| `phil_helpdesk_ticket` | `/{id}` | Une demande : son demandeur, sa file, son statut, son responsable et sa conversation, les formulaires qui y répondent, l'assignent, appliquent une transition et la déplacent |
| `phil_helpdesk_reply`, `phil_helpdesk_assign`, `phil_helpdesk_transition`, `phil_helpdesk_move` | `/{id}/reply`, `/{id}/assign`, `/{id}/transition/{transition}`, `/{id}/move` | Soumises par ces formulaires |

Chaque formulaire est soumis avec le jeton CSRF sans état de Symfony `phil_helpdesk` ; une requête que le navigateur dit
provenir d'un autre site est refusée. Un changement refusé est dit sur la page, avec le statut 422. Les pages ne sont pas
mises en cache ; leurs libellés existent en anglais et en français, dans le domaine de traduction `phil_helpdesk`, où
les libellés des files sont aussi traduits.
