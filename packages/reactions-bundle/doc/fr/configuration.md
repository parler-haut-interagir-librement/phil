<!-- phil-dev source-sha256=a6e317fc4f3c794acfdef1db7af0b06cd2fa5150d706366bae9c9cba43d50809 -->
# Configuration

Chaque option, avec sa valeur par défaut :

```yaml
# config/packages/phil_reactions.yaml
phil_reactions:
    store: doctrine              # or memory, for tests: emptied between two requests
    connection: default          # the Doctrine connection of the table
    unicode:                     # replaces the six default positive reactions when given
        -
            code: like           # lower-case letters, digits, "_", "+" or "-", starting with a letter
            emoji: '👍'
            label: reaction.like # a translation key
            private: false       # no public counter and no names
            quick: true          # in the bar rather than in the picker
            translation_domain: phil_reactions
    custom: []                   # the emojis of the site
    #   -
    #       code: gnome
    #       image: /emojis/gnome.webp # a path, an HTTPS address, or media: and an identifier
    #       alt: emoji.gnome.alt      # a translation key
    #       label: emoji.gnome        # a translation key
    #       private: false
    #       quick: false
    #       translation_domain: messages
    order: []                    # the names of the sources shown first: unicode, site, those of the application
    visibility:
        panel: everyone          # everyone, members or nobody: who sees the reactions at all
        counts: everyone         # who sees the counters
        names: nobody            # who sees the names of those who reacted
    access: ~                    # a ReactionAccess service; the voters with Security, nobody otherwise
    current_member: ~            # a CurrentMember service giving a stable identifier, never an email address; required with Security, nobody otherwise
    member_names: ~              # a MemberNames service, needed to show names
    visibility_policy: ~         # a VisibilityPolicy service, to decide content by content
    rate_limiter: limiter.phil_reactions # a rate limiter factory
    messenger: false             # whether the events are also sent on the message bus
    kit_subdirectory: ~          # where the recipes are; the subdirectory of ux_toolkit when null
    per_member: 1                # how many reactions a member gives one content: 1, replaced by a new one, up to 20
```

Le limiteur `phil_reactions` permet 30 changements par minute par membre. Une application le change en déclarant le
même nom :

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        phil_reactions: { policy: token_bucket, limit: 10, rate: { interval: '10 seconds' } }
```

Le stockage `memory` porte l'étiquette `kernel.reset` : ce qu'une requête y écrit a disparu à la suivante, sous PHP-FPM
comme dans un *worker*, et n'atteint donc jamais un autre membre. Un test qui lit ce qu'une requête antérieure a écrit
emploie le stockage Doctrine, sur SQLite.

## Services

| Service | Public | Ce que c'est |
|---|---|---|
| `Reactions` | oui | Réagir, retirer, effacer un contenu |
| `ReactionBoard` | oui | Ce que voit celui qui regarde, et `preload()` |
| `ReactionCatalog` | oui | Les réactions du site |
| `ReactionRepository` | oui | Le stockage |
| `ReactionsPerMember` | non | Le plafond de `per_member` |
| `ReactionAccess`, `CurrentMember`, `MemberNames`, `VisibilityPolicy`, `ReactionThrottle`, `EmojiImages` | non | Les ports |
| `phil_reactions.twig.intl_extension` | non | L'extension de twig/intl-extra, quand TwigExtraBundle ne l'enregistre pas |

`ReactionBoard`, `ReactionCatalog` et le stockage `memory` sont réinitialisés entre deux requêtes, `kernel.reset` ; les
autres services ne retiennent rien d'une requête à la suivante.

Quand `phil/settings-bundle` est présent, les réglages du site `reactions.panel`, `reactions.counts` et
`reactions.names` choisissent le public ; laissés à `configured`, ils gardent la configuration.
