# Reference

## Announcing from a migration

| Type | Kind | Role |
|---|---|---|
| `AnnouncingMigration` | abstract class | A Doctrine `AbstractMigration` with `note()` and `news()` |
| `Announcing` | interface | `announceWith(Announcements)`: what the factory calls |
| `Announces` | trait | `announceWith()`, `note()` and `news()`, for a base class of an application's own |
| `News` | `final readonly class` | A title, its lines and, maybe, a URL |
| `AnnouncementsMissing` | `LogicException` | A migration announced without being given the announcements |

`note()` takes one line per locale — `['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']` — and
`news()` one `News` per locale. A locale is a language, and maybe a region: `fr` or `fr_CA`. A note is a line of
text; a piece of news has a title, and its URL is a URL or none. Anything else is an `InvalidAnnouncement`.

An application that has a base migration of its own uses the trait:

```php
abstract class SiteMigration extends AbstractMigration implements Announcing
{
    use Announces;
}
```

## What a run announces

| Type | Kind | Role |
|---|---|---|
| `Announcements` | `final class` | The notes and news of a run, kept by the migration that left them |
| `Release` | `final readonly class` | What the run announces, in the order the migrations ran; `notesIn()` and `newsIn()` by locale |
| `AnnouncingMigrationFactory` | decorates `MigrationFactory` | Gives the announcements to every `Announcing` migration |
| `AnnouncingMigrator` | decorates `Migrator` | Announces the run once it is over |

The migrator clears the announcements as a run starts, then, once it is over, keeps the notes and news of the
migrations that ran to the end — neither skipped nor failed — in the order of the plan:

| The run | What is announced |
|---|---|
| ran | every migration that ran to the end |
| `--dry-run` | nothing |
| failed, all or nothing | nothing: everything was rolled back |
| failed, not all or nothing | the migrations that ran before the failure, which stay |

Announcing comes after Doctrine committed, so a migration announced is a migration kept.

## Channels

| Type | Kind | Role |
|---|---|---|
| `Channel` | `final readonly class` | Where and how a release is announced |
| `Content` | enum | `Notes` or `News` |
| `Announcer` | `final readonly class` | Announces a release on every channel |
| `Publisher` | interface | Sends one message on a transport |
| `ChatterPublisher` | `final readonly class` | Publishes through the chatter of `symfony/notifier` |

| `Channel` argument | Default | Role |
|---|---|---|
| `transport` | | The chatter transport, as `symfony/notifier` names it |
| `locale` | | The locale the announcement is read in; a note or a piece of news not written in it is left out |
| `content` | `Content::Notes` | The release notes, or the news |
| `title` | `''` | The title of the first message; none when empty |
| `continued` | `''` | The title of the messages that follow; none when empty |
| `heading` | `'{title}'` | The line of a piece of news that holds its title |
| `link` | `'{url}'` | The line that holds its URL; none when empty or when the news has no URL |
| `maxLength` | `2000` | The longest message, in characters: 2000 on Discord; 100 at least |

A title takes half of a message at most. `{counter}` in `title` or `continued` becomes ` (2/3)` when the release
takes more than one message, and nothing when it takes one.

A channel that fails is logged as an error and left; the other channels are still told, and the migrations, which
already ran, stay.

## How messages are cut

The lines of a channel are put together, one per line, as long as the message, title and blank line included, fits
`maxLength`; the next line starts the next message. A line too long alone is cut at its last space that fits, or in
the middle of a word when none does. The room a title takes is counted with its counter, so a release of ten
messages makes room for ` (10/10)`. Lengths are counted in characters, never in bytes.
