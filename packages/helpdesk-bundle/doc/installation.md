# Installation

## Requirements

PHP 8.5 or later, Symfony 8.2 or later with its Workflow component, Doctrine DBAL 4 with DoctrineBundle, and
phil/conversation-bundle, installed and configured with its key management service — see its installation guide. For
the pages: Twig, Symfony UX Twig Component, Routing, Symfony Security's CSRF protection, and the recipes `Button`,
`Badge`, `Input`, `NativeSelect` and `Textarea` of the kit. Optional: Symfony Security, for the agents named by role;
the Doctrine ORM, for the table added to the schema it generates; EasyAdminBundle with `twig/extra-bundle`;
`phil/notifications-bundle` and `phil/privacy-bundle`.

## Enabling the bundle

```sh
composer require phil/helpdesk-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\ConversationBundle\PhilConversationBundle::class => ['all' => true],
    Phil\HelpdeskBundle\PhilHelpdeskBundle::class => ['all' => true],
];
```

## The recipe

The Flex recipe of the bundle registers it in `config/bundles.php`, writes `config/packages/phil_helpdesk.yaml`, with
one queue, `general`, whose agents hold `ROLE_SUPPORT`, and imports the pages of the requesters under `/help` and the
desk of the agents under `/support-desk` in `config/routes/phil_helpdesk.yaml`. The recipe lives in the recipes
repository of PHIL, read once the application lists that repository in `extra.symfony.endpoint`; without it, register
the bundle and write the configuration by hand.

## The table

The bundle keeps one table through DBAL, `phil_helpdesk_ticket`, on the connection `phil_helpdesk.connection` names.
With the Doctrine ORM, it is added to the schema it generates for that connection, so that the application's migrations
create it, beside the tables of the conversations:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, `HelpdeskTables::configure()` adds it to a `Schema`, as `ConversationTables::configure()` does for the
conversations.

| Column | Holds |
|---|---|
| `conversation_id` | The conversation the request is, its primary key |
| `queue` | The name of its queue |
| `status` | Its place in the workflow: `new`, `open`, `waiting`, `resolved` or `closed` |
| `requester` | The identifier of its requester, null once their data is erased |
| `assignee` | The identifier of its agent, or null |
| `opened_at`, `updated_at`, `closed_at` | When it was opened, last changed and closed, in UTC |

## The routes

```yaml
# config/routes/phil_helpdesk.yaml
phil_helpdesk:
    resource: '@PhilHelpdeskBundle/config/routes.php'
    prefix: /help

phil_helpdesk_agents:
    resource: '@PhilHelpdeskBundle/config/routes_agents.php'
    prefix: /support-desk
```

Put both prefixes behind the application's firewall: without a signed-in participant, the pages are denied, and the desk
is denied to someone who works no queue.

## EasyAdmin

With EasyAdminBundle registered, the desk is also grafted on every dashboard, through `#[AdminRoute]`, under routes
EasyAdmin names after the dashboard's — `admin_helpdesk_desk` under a dashboard whose route is `admin` — and rendered in
its layout. The pages call the same services as outside the back office. The dashboard's menu links to it:

```php
use Phil\HelpdeskBundle\Infrastructure\EasyAdmin\HelpdeskAdmin;

public function configureMenuItems(): iterable
{
    yield HelpdeskAdmin::menuItem('admin');
}
```

The application imports `routes_agents.php` only if it wants the desk outside the back office too.

## The recipes of the kit

The pages draw their buttons, statuses, fields and text areas with the recipes of the kit, installed by the Symfony UX
Toolkit and used as they are:

```sh
php bin/console ux:install button badge input native-select textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The bundle reads the components subdirectory of the Toolkit's configuration; `phil_helpdesk.kit_subdirectory` names
another.
