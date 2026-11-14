<!-- phil-dev source-sha256=e8898ac0e5359240aeaf1a22a12e3fe56f165e9cc1d09aa7a237ccc9beed2300 -->
# Configuration

Chaque option, avec sa valeur par défaut :

```yaml
# config/packages/phil_settings.yaml
phil_settings:
    store: dbal                 # or memory, for tests
    connection: default         # the Doctrine connection of the table
    current_user: ~             # a CurrentUser service; Symfony Security's user when null
    template: '@PhilSettings/settings.html.twig'
    site_role: ROLE_ADMIN       # the role that changes a site's setting that names none
    cookie:
        name: phil_preferences
        lifetime: 31536000      # a year, in seconds; at least 60
    display: true               # the display preferences
    notifications:
        enabled: true           # the notification preferences, with phil/notifications-bundle
        timezone: UTC           # the time zone of the quiet hours
    sections:
        author:
            label: ''           # a translation key; section.author when empty
            help: ~             # a translation key
            translation_domain: messages
            before: [notifications]
            after: []
    settings:
        author.signature:
            type: text          # boolean, integer, text, choice or choices
            default: ~          # false, the minimum or the integer nearest 0, '', the first choice or none
            scope: user         # or site
            section: general
            label: ''           # a translation key; author.signature.label when empty
            help: ~             # a translation key
            translation_domain: messages
            choices: []         # the values of a choice, in their order
            min: ~              # an integer's bounds
            max: ~
            max_length: 255     # a text's longest length, up to 2000
            pattern: ~          # the regular expression a text matches
            role: ~             # the role that changes a site's setting; site_role when null
            anonymous: false    # whether a visitor keeps a boolean or a choice, in the cookie
            html_attribute: ~   # the name after data- a choice writes on the root element
            before: []          # the settings of its section it comes before
            after: []           # and after
```

Le bundle déclare les sections `general`, `notifications`, `privacy` et `display`, dans cet ordre. Une section sans
réglages n'est pas affichée. Une déclaration qui se contredit, une clé déclarée deux fois ou une section qui n'existe
pas arrête le conteneur en donnant sa raison. Voir [Réglages](settings.md).

Quand `phil/notifications-bundle` est installé, le bundle règle l'option `preferences` de ce bundle sur son propre
service, sauf si `notifications` vaut `false` ou si `notifications.enabled` vaut `false`. Une option `preferences`
fournie par l'application l'emporte.
