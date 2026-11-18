# Changelog

All notable changes to `phil/mentions-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.0-alpha: subjects, members and authors of the mentions compare byte by byte on MySQL and
MariaDB. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Added

- `Infrastructure\Doctrine\BinaryKeysListener`, on the ORM's `loadClassMetadata` event, and its `FIELDS`: the fields of
  the bundle's entities that compare byte by byte on MySQL and MariaDB.

### Changed

- On MySQL and MariaDB, `id`, `subject`, `member` and `author` of `phil_mention` take the `utf8mb4` character set and
  the `utf8mb4_bin` collation in the schema the ORM generates, through `Infrastructure\Doctrine\BinaryKeysListener`,
  registered with the Doctrine store. PostgreSQL and SQLite keep their default, which compares bytes already.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, the mentions of `member:Ann` were listed for
  `member:ann`, and the unique key of the subject and the member refused to record the mention of one once the other
  was mentioned in the same subject.

## 0.1.0-alpha - 2026-11-14

### Added

- `Mention`: a handle typed after `@` at a character offset, or a UJO mention mark naming its member by reference;
  `MentionPattern`: what a handle looks like, 1 to 64 letters of any script, digits, `_`, `.` and `-` by default, or
  the application's expression, checked once; `InvalidMention`, `MentionsRefused` and `MentionsException` for every
  exception of the bundle.
- `TextMentions`: the handles of a plain text or of Markdown, its code blocks, code spans, link destinations and
  links in angle brackets left unread; `UjoMentions`: the mention marks of a UJO (Universal JSON Output) document.
- `RecordedMention`, mapped for Doctrine as the table `phil_mention`, one row per subject and member;
  `MentionRepository`, whose change leaves out a member the subject already mentions and returns the mentions added;
  `MemberIdentifier`, which refuses a member or an author named by an email address; `DoctrineMentionRepository`, on
  the entity manager, in one transaction, adding its rows by an upsert (`MentionInsert`: `ON CONFLICT DO NOTHING` on
  PostgreSQL and SQLite, `ON DUPLICATE KEY UPDATE` on MySQL and MariaDB), tested on SQLite, MySQL 8 and PostgreSQL 17;
  `InMemoryMentionRepository`, for tests, tagged `kernel.reset` and emptied between requests.
- `MemberDirectory`, the application's members found by handle or reference, searched and asked whether they may see
  a content; `Member`, whose reference and recipient are stable identifiers, never holding `@`.
- `MentionRecorder`: the mentions of a subject kept in step with its text, the author and the members who may not see
  the content left out, the members mentioned for the first time announced with `MemberMentioned` — once, by the request
  that stored the mention, when two record the same subject at once —; `Mentions`, the
  application's entry point for texts and UJO documents, the mentions of a subject and of a member, a subject
  forgotten; `MentionChanges`; `MentionLimits`, the members per text and the length of a text.
- `NotifyMentionedMember`: the notification type `member.mentioned` of `phil/notifications-bundle` for each new
  mention, on the centre and email, and Web Push when the notifications bundle sends it, with its English and French
  translations; `MentionPreferences` and `AlwaysNotify`; `SettingsMentionPreferences`, the setting
  `notifications.mentions` of `phil/settings-bundle`.
- `MentionLinker` and the Twig filter `mentions`, declared by `#[AsTwigFilter]` on `MentionsExtension` and loaded only
  when a template calls it: handles linked to the members' profiles, outside links, code, scripts, styles and text
  areas.
- `MemberReferenceSearch`: the `@` menu of `phil/block-editor-bundle` on the directory; `CommentMentions`, the
  mentions of `phil/comments-bundle` comments on the resource of their thread, and `MemberAuthorDirectory`, their
  authors named from the directory; `RecordCommentMentions`, the bridge registered when both bundles are installed,
  which records the mentions of each comment written or edited — on a passage or on the whole page — and forgets those
  of each comment deleted.
- `PhilMentionsBundle`, configured under `phil_mentions`: `store`, `entity_manager`, `member_directory`, `pattern`,
  `limits`, `notification_type`, `preferences`, `comments.markdown` and `comments.record`; the table mapped for
  Doctrine, the notification type, the setting and the editor's search declared to their bundles when enabled, and the
  controller and stylesheet mapped in the extension of AssetMapperBundle when it is enabled; `MentionsPass`.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_mentions.yaml` naming
  `App\Mentions\Members`, and that directory copied to `src/Mentions/`, which finds nobody until it reads the
  application's accounts.
- The `mention` Stimulus controller: an accessible `@` autocomplete on plain text fields, from the block editor's
  member search, a combobox controlling a listbox, moved with the keyboard, with a polite live region that says how
  many members were found, and its stylesheet, fetched lazily; the browser suite that drives it in Chrome with
  `symfony/panther`.
