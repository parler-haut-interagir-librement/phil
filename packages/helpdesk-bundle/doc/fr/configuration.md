<!-- phil-dev source-sha256=d3cb95668f29223c192f07874e87767377650b0f293f80423af1acb3d94327b7 -->
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

Chaque file nomme les rôles de ses agents. Avec le `QueueAccess` par défaut, la personne connectée travaille une file
quand le vérificateur d'autorisation de Symfony Security lui accorde l'un quelconque de ses rôles — hiérarchie des rôles
et voters compris —, et personne ne travaille une file hors d'une requête. Un agent ne voit que les demandes des files
qu'il travaille ; une demande d'une autre file est introuvable. Les demandeurs choisissent une file parmi toutes.

## `queue_access`

Un service de l'application qui décide autrement qui travaille une file : une équipe, un horaire. Voyez
[Points d'extension](extension-points.md#queueaccess).

## `canned_replies` et `canned_reply_source`

Voyez [Réponses types](canned-replies.md). Sans l'une ni l'autre, aucune réponse type n'est proposée.

## `layout` et `agent_layout`

Les pages des demandeurs étendent `layout`, le bureau étend `agent_layout` hors d'EasyAdmin ; dans une interface
d'administration, le bureau produit son rendu dans la mise en page du tableau de bord. La valeur par défaut est une page
HTML nue.

## `notifications`

Avec phil/notifications-bundle enregistré, le type lui est déclaré avec les canaux `site` et `email`, et un agent est
averti quand quelqu'un d'autre lui assigne une demande. `enabled: false` le désactive. Les messages de la conversation
sont notifiés par phil/conversation-bundle, comme pour toute conversation.
