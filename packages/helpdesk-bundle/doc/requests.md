# Requests

## A request is a conversation

`Helpdesk` is the entry point. A request opens a conversation of phil/conversation-bundle with its requester alone,
through `Conversations::startOpen()`, and keeps beside it a queue, a status and an assignee:

```php
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;

$ticket = $helpdesk->open(ParticipantId::fromString('member-7'), QueueName::fromString('billing'), 'Wrong invoice', 'I was charged twice.');
```

The request is named by its conversation's identifier, `$ticket->conversation`. Its subject and messages are those of
the conversation: sealed at rest, read and unread, notified to its participants. An undeclared queue throws
`InvalidQueue`; a subject or a body the conversations refuse throws their `InvalidConversation`.

## The requester

```php
$helpdesk->requests($requester);                    // their requests, latest change first, by pages of 50
$helpdesk->ofRequester($id, $requester);            // one of them; someone else's is not found
$helpdesk->answer($id, $requester, 'Any news?');
$helpdesk->withdraw($id, $requester);               // closes it
```

## The agents

An agent works the queues `QueueAccess` grants the person signed in:

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

An agent who answers or is assigned joins the conversation: they receive its next messages, and the requester sees
them as a participant. A request of a queue the agent does not work throws `TicketNotFound`, as one that does not exist.

## The workflow

The status moves through the Symfony Workflow state machine `phil_helpdesk_ticket`:

| Transition | From | To | Applied |
|---|---|---|---|
| `take` | new | open | when the request is assigned, or by an agent |
| `wait` | new, open | waiting | when an agent answers |
| `answer` | waiting, resolved | open | when the requester answers |
| `resolve` | new, open, waiting | resolved | by an agent |
| `reopen` | resolved | open | by an agent |
| `close` | new, open, waiting, resolved | closed | by an agent, or the requester |

A closed request takes no more messages: an answer or a reply throws `TransitionRefused`, as a transition the workflow
does not allow from where the request stands. The workflow dispatches the events of the component through the
application's event dispatcher, so a guard holds a transition and a listener follows it:

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

## Events

`TicketOpened` follows each request opened, `TicketAssigned` each assignment, both by identifiers only; the messages
of a request are the `MessageSent` of phil/conversation-bundle, and its changes of status the events of the workflow.

## The pages

The requesters' routes, from `config/routes.php`:

| Route | Path | What it shows |
|---|---|---|
| `phil_helpdesk_requests` | `/` | The requester's requests and their status |
| `phil_helpdesk_new` | `/new` | The form that opens a request: queue, subject, question |
| `phil_helpdesk_request` | `/{id}` | A request: its queue, status and conversation, the forms that answer and close it |
| `phil_helpdesk_answer`, `phil_helpdesk_withdraw` | `/{id}/answer`, `/{id}/withdraw` | Posted by those forms |

The agents' routes, from `config/routes_agents.php`, and grafted on the dashboards of EasyAdmin:

| Route | Path | What it shows |
|---|---|---|
| `phil_helpdesk_desk` | `/` | The desk: the requests of the agent's queues, filtered by queue, status and "assigned to me" |
| `phil_helpdesk_ticket` | `/{id}` | A request: its requester, queue, status, assignee and conversation, the forms that answer, assign, apply a transition and move it |
| `phil_helpdesk_reply`, `phil_helpdesk_assign`, `phil_helpdesk_transition`, `phil_helpdesk_move` | `/{id}/reply`, `/{id}/assign`, `/{id}/transition/{transition}`, `/{id}/move` | Posted by those forms |

Every form is posted with Symfony's stateless CSRF token `phil_helpdesk`; a request a browser says comes from another site
is refused. A refused change is said on the page, with the status 422. The pages are not cached; their labels come in
English and French, in the translation domain `phil_helpdesk`, where the labels of the queues are translated too.
