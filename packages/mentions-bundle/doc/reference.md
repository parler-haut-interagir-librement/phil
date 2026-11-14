# Reference

## Domain

| Class | Role |
|---|---|
| `Domain\ValueObject\Mention` | A handle at an offset, or a member named by a mark |
| `Domain\ValueObject\MentionPattern` | What a handle looks like, and the expression that finds it after `@` |
| `Domain\Model\RecordedMention` | A member mentioned in a subject, mapped as `phil_mention` |
| `Domain\Contract\MentionRepository` | The store of mentions |
| `Domain\Exception\MentionsException` | Every exception of the bundle |
| `Domain\Exception\InvalidMention` | What a mention, a pattern, a member or a limit cannot be made of |
| `Domain\Exception\MentionsRefused` | A text too long, or mentioning too many members |

## Application

| Class | Role |
|---|---|
| `Application\Contract\MemberDirectory` | The application's members |
| `Application\Contract\MentionPreferences` | Whether a member wants to be notified |
| `Application\Member` | A member as the directory gives it |
| `Domain\ValueObject\MemberIdentifier` | A member's or an author's identifier, refused when it holds `@` |
| `Application\Extraction\TextMentions` | The handles of a plain text or of Markdown |
| `Application\Extraction\UjoMentions` | The mention marks of a UJO document |
| `Application\MentionLimits` | The members per text and the length of a text |
| `Application\MentionRecorder` | The mentions of a subject kept in step with its text |
| `Application\MentionChanges` | The members a recording added and removed |
| `Application\Mentions` | The application's entry point |
| `Application\Event\MemberMentioned` | A member newly mentioned |
| `Application\AlwaysNotify` | Every member notified |
| `Application\Rendering\MentionLinker` | Handles of HTML linked to profiles |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Doctrine\DoctrineMentionRepository` | The table, through the entity manager, each change in one transaction |
| `Infrastructure\Doctrine\MentionInsert` | The upsert of a row for each platform |
| `Infrastructure\Memory\InMemoryMentionRepository` | Mentions in memory, emptied between requests (`kernel.reset`) |
| `Infrastructure\Notifications\NotifyMentionedMember` | The notification of a new mention |
| `Infrastructure\Settings\SettingsMentionPreferences` | The setting `notifications.mentions` |
| `Infrastructure\Twig\MentionsExtension` | The `mentions` filter, declared by `#[AsTwigFilter]` and loaded when called |
| `Infrastructure\BlockEditor\MemberReferenceSearch` | The editor's `@` menu on the directory |
| `Infrastructure\Comments\CommentMentions` | The mentions of comments |
| `Infrastructure\Comments\MemberAuthorDirectory` | Comment authors named from the directory |
| `Infrastructure\Comments\RecordCommentMentions` | The bridge: the mentions of each comment written, edited or deleted |
| `Infrastructure\DependencyInjection\MentionsServices` | The services |
| `Infrastructure\DependencyInjection\MentionsPass` | The notification and the preferences, by the bundles enabled |
| `PhilMentionsBundle` | The configuration and what is prepended to the other bundles |

## JavaScript

| Module | Role |
|---|---|
| `assets/src/mention_controller.js` | The `@` autocomplete |
| `assets/src/lib/query.js` | The query before the caret, the handle inserted, a suggestion's handle |
| `assets/src/lib/api.js` | The member search |
| `assets/styles/mentions.css` | The list and `.mention` |

## The recipe

| File | Role |
|---|---|
| `recipe/0.1/manifest.json` | The Flex manifest: the bundle registered, the configuration and the directory copied |
| `recipe/0.1/config/packages/phil_mentions.yaml` | `member_directory: App\Mentions\Members` |
| `recipe/0.1/src/Mentions/Members.php` | A directory that finds nobody, for the application to fill |
