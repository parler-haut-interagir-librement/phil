# phil/reactions-bundle

**Reactions to any content for Symfony applications, as a Live Component that also works as a plain form.**

phil/reactions-bundle lets the members of a site react to an article, a page, a post or anything else the application
can name. Each member has one reaction per content by default, and can change it or take it back; a site that wants
more lets each member give several, each emoji once, up to a cap it chooses. The site says which
reactions it offers, in labelled sources — Unicode emojis and its own emojis, each an image with a replacement text —
and who sees the reactions, their counters and the names of those who reacted. A reaction can be private, such as a
"not useful": it never has a public counter or names, so a site offers no reaction counted against a content in
public.

It is for any Symfony application with content to respond to: a blog, documentation, a forum, a wiki, announcements.
The application names its contents as references of `phil/resource` (`article:42`), its members by a stable
identifier — never an email address — and writes one voter; the bundle
keeps the reactions, draws them with the recipes of the kit it requires, and refuses every reaction until a voter says
otherwise.

A page of many contents reads them in one query for each thing to know. Every button is the button of a plain form the
component takes over, so reacting works without JavaScript; the picker adds a search and the keyboard by Stimulus. The
services hold nothing between two requests of a worker.

## Status

| Part | State |
|---|---|
| `Reaction`, one per member and per content or several up to a cap, its moment in UTC; Doctrine store, and in-memory store emptied between two requests | Implemented and tested, on SQLite |
| A member named by a stable identifier of the site, `@` refused | Implemented and tested |
| The list in sources, private reactions, the visibility | Implemented and tested |
| `Reactions`, the voter attribute, the rate limit, the events | Implemented and tested |
| `ReactionBoard`, the counters of a page read at once, the names as a list in the language of the page | Implemented and tested, with a benchmark and a page measured in worker mode |
| The Live Component `PhilReactions` and the plain form with its CSRF token and signed address | Implemented and tested, with each kit |
| The Stimulus controllers, fetched lazily, and their paths for AssetMapper | Implemented and tested under Node, with AssetMapperBundle and StimulusBundle, and in a headless Chrome; the checks a person makes with a screen reader listed in [testing](doc/testing.md) |
| The erasure and the export of a member's reactions, images as media read in one query, visibility among the settings | Implemented and tested, when their package is there |
| Notifications, moderation, reputation | Not part of the bundle: they listen to the events or read the store |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, the table, the routes, the recipes, the stylesheet. |
| [Configuration](doc/configuration.md) | Every option, the services registered. |
| [Reactions](doc/reactions.md) | The list, several reactions per member, private reactions, the visibility, the member, the voter, the rate limit. |
| [The component](doc/component.md) | The Live Component, the plain form, a page of many contents, the accessibility, the picker. |
| [Events](doc/events.md) | The events, the message bus, the erasure. |
| [Extension points](doc/extension-points.md) | What an application implements, configures or replaces. |
| [Testing](doc/testing.md) | The tests, the benchmark, the checks in a browser. |
| [Reference](doc/reference.md) | Every class and file of the bundle. |

## Quick start

```sh
composer require phil/reactions-bundle
php bin/console ux:install reaction-button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install reaction-picker --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/routes/phil_reactions.yaml
phil_reactions:
    resource: '@PhilReactionsBundle/config/routes.php'
    prefix: /_reactions
```

```yaml
# config/packages/phil_reactions.yaml
phil_reactions:
    current_member: App\Reactions\AccountMember # gives the stable identifier of the signed-in member
```

```php
final class ReactionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return Reactions::REACT === $attribute && $subject instanceof ReactionSubject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof UserInterface;
    }
}
```

```twig
{{ reactions_preload(articles|map(article => 'article:' ~ article.id)) }}
{% for article in articles %}
    <twig:PhilReactions content="article:{{ article.id }}" />
{% endfor %}
```

## Requirements

PHP 8.5 or later with `ext-intl`, Symfony 8.2 or later, Symfony UX Live Component 3.7 or later, twig/intl-extra,
Doctrine DBAL 4.5 and ORM 3.7. Symfony Security, Messenger, AssetMapper, `phil/media-bundle`, `phil/settings-bundle`
and `phil/privacy-bundle` are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
