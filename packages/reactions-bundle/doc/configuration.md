# Configuration

Every option, with its default:

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

The limiter `phil_reactions` allows 30 changes a minute per member. An application changes it by declaring the same
name:

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        phil_reactions: { policy: token_bucket, limit: 10, rate: { interval: '10 seconds' } }
```

The store `memory` is tagged `kernel.reset`: what a request writes is gone at the next one, under PHP-FPM as in a
worker, so it never reaches another member. A test that reads what an earlier request wrote uses the Doctrine store, on
SQLite.

## Services

| Service | Public | What it is |
|---|---|---|
| `Reactions` | yes | Reacting, withdrawing, erasing a content |
| `ReactionBoard` | yes | What a viewer sees, and `preload()` |
| `ReactionCatalog` | yes | The reactions of the site |
| `ReactionRepository` | yes | The store |
| `ReactionsPerMember` | no | The cap of `per_member` |
| `ReactionAccess`, `CurrentMember`, `MemberNames`, `VisibilityPolicy`, `ReactionThrottle`, `EmojiImages` | no | The ports |
| `phil_reactions.twig.intl_extension` | no | The extension of twig/intl-extra, when TwigExtraBundle does not register it |

`ReactionBoard`, `ReactionCatalog` and the store `memory` are reset between two requests, `kernel.reset`; the other
services hold nothing from one request to the next.

When `phil/settings-bundle` is there, the settings of the site `reactions.panel`, `reactions.counts` and
`reactions.names` choose the audience; left at `configured`, they keep the configuration.
