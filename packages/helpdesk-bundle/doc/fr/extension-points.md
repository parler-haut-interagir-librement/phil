<!-- phil-dev source-sha256=710a96a6ac5b9dfc8428903d4a9df26f533d7b7e3901edcb89429ab7cd3e25ec -->
# Points d'extension

Le bundle est dans ses versions `0.x` : les ports, le workflow, les événements et les services ci-dessous peuvent
changer dans une version mineure, ce que dit le journal des modifications. Ils sont destinés à être stables à partir de
la version `1.0`.

## `QueueAccess`

Si la personne connectée travaille une file, dans `Phil\HelpdeskBundle\Application\Contract`. `RoleQueueAccess` l'accorde
aux détenteurs de l'un quelconque des rôles de la file, par Symfony Security ; une application nomme son propre service
sous `phil_helpdesk.queue_access`.

```php
interface QueueAccess
{
    public function mayWork(Queue $queue): bool;
}
```

## `CannedReplies`

Les réponses types proposées pour une demande, et le texte de l'une d'elles. `TwigCannedReplies` lit les gabarits de la
configuration, `NoCannedReplies` n'en propose aucune ; voyez [Réponses types](canned-replies.md).

```php
interface CannedReplies
{
    /** @return list<CannedReply> */
    public function for(Ticket $ticket): array;

    public function render(string $id, Ticket $ticket, ?string $locale = null): string;
}
```

## Les ports de phil/conversation-bundle

Qui est connecté, `CurrentParticipant`, et les noms des participants, `ParticipantDirectory`, sont ceux de
phil/conversation-bundle : les demandeurs et les agents sont des participants des conversations.

## Le workflow

`phil_helpdesk_ticket`, construit par `TicketWorkflow`, émet les événements du composant Workflow de Symfony par le
répartiteur d'événements de l'application : `workflow.phil_helpdesk_ticket.guard.<transition>` pour retenir une
transition, `…completed.<transition>` pour en suivre une. Voyez [Demandes](requests.md#le-workflow).

## `TicketRepository`

Le dépôt, dans `Phil\HelpdeskBundle\Domain\Repository`, avec son implémentation DBAL. Un autre dépôt l'implémente et en
reçoit l'alias ; il garde les instants en UTC et lit les files en commençant par le dernier changement.

## Événements

`TicketOpened` et `TicketAssigned`, dans `Phil\HelpdeskBundle\Application\Event`, par identifiants seulement.

## Gabarits et traductions

Les pages sont les gabarits `@PhilHelpdesk/requests.html.twig`, `new.html.twig`, `request.html.twig`, `desk.html.twig`,
`ticket.html.twig` et `_status.html.twig`, qu'une application remplace dans `templates/bundles/PhilHelpdeskBundle/`, et
les mises en page que nomme la configuration. Leurs libellés, et ceux des files, sont dans le domaine de traduction
`phil_helpdesk`.
