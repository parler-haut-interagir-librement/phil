# Configuration

```yaml
# config/packages/phil_helpdesk.yaml
phil_helpdesk:
    # The DBAL connection of the table.
    connection: default

    # The queues, by name: 1 to 64 lowercase letters, digits, dashes and underscores. Required, at least one.
    queues:
        billing:
            label: queue.billing    # the translation key of the label, in the domain "phil_helpdesk"
            roles: [ROLE_BILLING]   # the roles whose holders work the queue; any one of them is enough
        tech:
            label: queue.tech
            roles: [ROLE_TECH, ROLE_ADMIN]

    # The QueueAccess service saying who works a queue; the roles above, through Symfony Security, when null.
    queue_access: ~

    # The canned replies, by identifier.
    canned_replies:
        thanks:
            label: Thanks for your patience
            template: helpdesk/thanks.txt.twig
            queues: []              # every queue when empty
    # A CannedReplies service that replaces the canned replies above.
    canned_reply_source: ~

    # The Twig templates the pages extend, with the blocks "title" and "main".
    layout: '@PhilHelpdesk/layout.html.twig'
    agent_layout: '@PhilHelpdesk/layout.html.twig'
    # Where the recipes of the kit are, under the components directory; the Toolkit's subdirectory when null.
    kit_subdirectory: ~

    # The notification of an agent a request is assigned to, with phil/notifications-bundle registered.
    notifications:
        enabled: true
        type: phil_helpdesk.assigned
```

## `queues`

Each queue names the roles of its agents. With the default `QueueAccess`, the person signed in works a queue when
Symfony Security's authorization checker grants them any one of its roles — role hierarchy and voters included —, and
nobody works a queue outside a request. An agent sees the requests of the queues they work only; a request of another
queue is not found. The requesters pick a queue among all of them.

## `queue_access`

A service of the application's that decides who works a queue otherwise: a team, a schedule. See
[Extension points](extension-points.md#queueaccess).

## `canned_replies` and `canned_reply_source`

See [Canned replies](canned-replies.md). Without either, no canned reply is offered.

## `layout` and `agent_layout`

The requesters' pages extend `layout`, the desk extends `agent_layout` outside EasyAdmin; inside a back office, the desk
renders in the dashboard's layout. The default is a bare HTML page.

## `notifications`

With phil/notifications-bundle registered, the type is declared to it with the channels `site` and `email`, and an agent
is notified when someone else assigns them a request. `enabled: false` turns it off. The messages of the conversation
are notified by phil/conversation-bundle, as for any conversation.
