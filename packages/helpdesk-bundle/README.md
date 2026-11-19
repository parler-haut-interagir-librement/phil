# phil/helpdesk-bundle

**A help desk for Symfony: requests that are conversations, with queues, statuses and canned replies.**

phil/helpdesk-bundle gives an application a help desk. A member asks for help: they pick a queue, write a subject and
their question, and the request opens. Each request is a conversation of phil/conversation-bundle — sealed at rest,
read and unread, notified — opened by the requester alone, with a queue, a status and an assignee. The agents of a queue
read its requests on their desk, answer in their own words or with a canned reply, take a request or assign it, move it
to another queue, resolve it and close it; when an agent answers, the agent joins the conversation and the request waits
for the requester, and when the requester answers, it comes back to the agents.

The status moves through a Symfony Workflow state machine, `phil_helpdesk_ticket`: new, open, waiting, resolved,
closed. Its events are those of the component, so an application holds a transition with a guard or follows one with a
listener. The queues and the roles of their agents come from the configuration, checked by Symfony Security's
authorization checker, or by the application's own `QueueAccess`. Canned replies are Twig templates of the
application, written for the request, offered in the queues they name; an adapter to the application's content
templates replaces them through the port `CannedReplies`.

It is for a site that answers its members: a shop's customer service, the support of an online service, the
volunteers of an association. The requesters' pages and the agents' desk work without JavaScript and draw their
buttons, statuses, fields and text areas with the recipes of the kit, installed by the Symfony UX Toolkit; with
EasyAdminBundle, the desk is also grafted on every dashboard, in its layout, through the same services.

The bundle keeps its table through DBAL, without the ORM, so that a long queue is read by page. phil/notifications-bundle
and phil/privacy-bundle are optional: with the first, an agent is notified of a request someone else assigned to them;
with the last, the requests take part in the export and the erasure of a member's data. The services keep no state
between requests and are safe in a long-running worker.

## Status

| Part | State |
|---|---|
| Requests as conversations of phil/conversation-bundle, opened by the requester alone, with a queue, a status and an assignee | Implemented and tested |
| The workflow `phil_helpdesk_ticket`: the agent's answer waits for the requester, the requester's answer brings the request back | Implemented and tested |
| Queues and their agents by role, through `QueueAccess`; take, assign, move, resolve, reopen, close | Implemented and tested |
| Canned replies: Twig templates of the application by queue, through the port `CannedReplies` | Implemented and tested |
| The DBAL store, the schema by `Schema::editor()`, moments in UTC; MySQL 8.4, PostgreSQL 17 and SQLite | Implemented and tested |
| The pages of the requesters and the desk of the agents, with the recipes of the kit, English and French | Implemented and tested |
| The desk grafted on the dashboards of EasyAdmin | Implemented and tested |
| The notification of an assignment through `phil/notifications-bundle` | Implemented and tested |
| The export and the erasure of a member's requests through `phil/privacy-bundle` | Implemented and tested |
| An adapter of `CannedReplies` to the content templates of the application | Through the port, by the application |
| The Flex recipe: the bundle, a queue and the routes | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the table, the routes, the recipes of the kit, EasyAdmin, the recipe. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Requests](doc/requests.md) | Opening, answering, the desk, assigning, moving, the workflow and its events, the pages. |
| [Canned replies](doc/canned-replies.md) | The templates of the configuration, and the port that replaces them. |
| [Extension points](doc/extension-points.md) | The ports, the workflow, the events, the services to replace, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, and testing code that uses it. |
| [Reference](doc/reference.md) | Every class and file of the bundle. |

## Quick start

```sh
composer require phil/helpdesk-bundle
php bin/console ux:install button badge input native-select textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_helpdesk.yaml
phil_helpdesk:
    queues:
        billing: { label: Billing, roles: [ROLE_BILLING] }
        tech: { label: Technical support, roles: [ROLE_TECH] }
    canned_replies:
        thanks: { label: Thanks for your patience, template: helpdesk/thanks.txt.twig }
```

```yaml
# config/routes/phil_helpdesk.yaml
phil_helpdesk:
    resource: '@PhilHelpdeskBundle/config/routes.php'
    prefix: /help
phil_helpdesk_agents:
    resource: '@PhilHelpdeskBundle/config/routes_agents.php'
    prefix: /support-desk
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later with its Workflow component, Doctrine DBAL 4, and phil/conversation-bundle with
its key management service.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
