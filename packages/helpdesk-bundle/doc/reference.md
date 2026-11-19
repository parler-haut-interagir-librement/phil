# Reference

## Bundle

| Class or file | Role |
|---|---|
| `PhilHelpdeskBundle` | The bundle: its configuration `phil_helpdesk`, the notification type declared to phil/notifications-bundle, the stateless CSRF token |
| `config/routes.php` | The routes of the requesters' pages |
| `config/routes_agents.php` | The routes of the agents' desk |
| `recipe/0.1` | The Flex recipe: the bundle, a queue and the routes |

## Domain

| Class | Role |
|---|---|
| `Domain\Model\Ticket` | A request: its conversation, queue, status, requester, assignee and moments |
| `Domain\ValueObject\QueueName` | The name of a queue |
| `Domain\Ticket\Status` | `New`, `Open`, `Waiting`, `Resolved` or `Closed` |
| `Domain\Repository\TicketRepository` | The store |
| `Domain\Exception\HelpdeskException` | What every failure of the help desk is |
| `Domain\Exception\InvalidQueue` | A malformed or undeclared queue |
| `Domain\Exception\TicketNotFound` | A request that does not exist or is not for the person asking |
| `Domain\Exception\TransitionRefused` | A change of status the workflow does not allow |
| `Domain\Exception\CannedReplyNotFound` | A canned reply not offered for the request |

## Application

| Class | Role |
|---|---|
| `Application\Helpdesk` | The entry point: open, answer, withdraw, the desk, reply, canned replies, assign, move, transitions |
| `Application\TicketWorkflow` | The state machine `phil_helpdesk_ticket` and its marking store |
| `Application\Queues`, `Queue` | The queues of the configuration |
| `Application\CannedReply` | A canned reply offered: its identifier and label |
| `Application\NoCannedReplies` | No canned reply |
| `Application\Contract\QueueAccess` | Whether the person signed in works a queue |
| `Application\Contract\CannedReplies` | The canned replies of a request, and their text |
| `Application\Event\TicketOpened`, `TicketAssigned` | The events, by identifiers |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Doctrine\HelpdeskTables` | The table, added to a `Schema` |
| `Infrastructure\Doctrine\DbalTicketRepository` | The DBAL store |
| `Infrastructure\Doctrine\SchemaListener` | Adds the table to the schema the ORM generates |
| `Infrastructure\Security\RoleQueueAccess` | The agents of a queue by role |
| `Infrastructure\Twig\TwigCannedReplies` | The canned replies of the configuration |
| `Infrastructure\Twig\HelpdeskExtension` | `phil_helpdesk_kit()` and `phil_helpdesk_name()` |
| `Infrastructure\Twig\KitComponents` | The names of the recipes of the kit |
| `Infrastructure\Http\RequesterController` | The requesters' pages |
| `Infrastructure\Http\AgentController` | The agents' desk, also grafted on EasyAdmin |
| `Infrastructure\Http\HelpdeskRequest` | The stateless CSRF check of the forms |
| `Infrastructure\EasyAdmin\HelpdeskAdmin` | The link of a dashboard's menu, and the routes and layout inside a back office |
| `Infrastructure\Notifications\NotifyAssignee` | Notifies an agent of a request assigned to them |
| `Infrastructure\Privacy\HelpdeskPrivacy` | The export and the erasure |
| `Infrastructure\DependencyInjection\HelpdeskServices` | The services of the configuration |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Reads the components subdirectory of the Toolkit |

## Templates and translations

| File | Role |
|---|---|
| `templates/layout.html.twig` | The default layout, with the blocks `title` and `main` |
| `templates/easyadmin/layout.html.twig` | The layout inside an EasyAdmin back office |
| `templates/requests.html.twig`, `new.html.twig`, `request.html.twig` | The requesters' pages |
| `templates/desk.html.twig`, `ticket.html.twig` | The agents' desk |
| `templates/_status.html.twig` | The status of a request, the recipe `Badge` |
| `translations/phil_helpdesk.en.php`, `phil_helpdesk.fr.php` | The labels |
