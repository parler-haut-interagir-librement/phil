# Extension points

The bundle is in its `0.x` releases: the ports, the workflow, the events and the services below may change in a minor
release, said in the changelog. They are meant to be stable from `1.0`.

## `QueueAccess`

Whether the person signed in works a queue, in `Phil\HelpdeskBundle\Application\Contract`. `RoleQueueAccess` grants it
to the holders of any role of the queue, through Symfony Security; an application names its own service under
`phil_helpdesk.queue_access`.

```php
interface QueueAccess
{
    public function mayWork(Queue $queue): bool;
}
```

## `CannedReplies`

The canned replies offered for a request, and the text of one. `TwigCannedReplies` reads the templates of the
configuration, `NoCannedReplies` offers none; see [Canned replies](canned-replies.md).

```php
interface CannedReplies
{
    /** @return list<CannedReply> */
    public function for(Ticket $ticket): array;

    public function render(string $id, Ticket $ticket, ?string $locale = null): string;
}
```

## The ports of phil/conversation-bundle

Who is signed in, `CurrentParticipant`, and the names of the participants, `ParticipantDirectory`, are those of
phil/conversation-bundle: the requesters and the agents are participants of the conversations.

## The workflow

`phil_helpdesk_ticket`, built by `TicketWorkflow`, dispatches the events of Symfony's Workflow component through the
application's event dispatcher: `workflow.phil_helpdesk_ticket.guard.<transition>` to hold a transition,
`…completed.<transition>` to follow one. See [Requests](requests.md#the-workflow).

## `TicketRepository`

The store, in `Phil\HelpdeskBundle\Domain\Repository`, with its DBAL implementation. Another store implements it and
is aliased to it; it keeps the moments in UTC and reads the queues latest change first.

## Events

`TicketOpened` and `TicketAssigned`, in `Phil\HelpdeskBundle\Application\Event`, by identifiers only.

## Templates and translations

The pages are the templates `@PhilHelpdesk/requests.html.twig`, `new.html.twig`, `request.html.twig`,
`desk.html.twig`, `ticket.html.twig` and `_status.html.twig`, which an application overrides in
`templates/bundles/PhilHelpdeskBundle/`, and the layouts the configuration names. Their labels, and those of the queues,
are in the translation domain `phil_helpdesk`.
