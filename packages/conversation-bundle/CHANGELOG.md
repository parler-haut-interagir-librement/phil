# Changelog

All notable changes to `phil/conversation-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- Direct and group conversations: two participants share one direct conversation, unique by the hash of the pair, and
  two first messages written at once go in the conversation the first one started, the other removed under a savepoint
  (`DirectConversationExists`); a group holds 2 to `limits.group_size` participants, its subject optional;
  `startOpen()` and `join()` for the packages built on the bundle.
- Participants named by a stable identifier, `ParticipantId`, which refuses an identifier with `@`.
- The subjects and bodies sealed at rest by Symfony's KeyManagement component, in the format
  `pcv1.a256gcm.<master key>.<envelope>`, whose version and algorithm are authenticated, each text bound to its row;
  `phil_conversation.encryption` required, the container stopped without its key management client.
- The rotation of the master key: `KeyRotation` and `phil:conversation:rotate-keys`, by batches, with `--dry-run`.
- Reception settings: everyone, contacts through `ContactRelation`, or nobody may start a conversation; up to 1 000
  blocks, either way, which also stop a direct conversation and the notifications of a group.
- Read and unread by sequence, the inbox and its unread count, muting and leaving; the inbox and the conversations
  read by page.
- `ConversationStarted` and `MessageSent`, by identifiers only.
- The DBAL stores, the five tables of `ConversationTables` built with `Schema::editor()` and added to the schema the ORM
  generates, moments in UTC; tested on SQLite, MySQL 8.4 and PostgreSQL 17.
- The pages: inbox, conversation, writing to a member, reception settings, with the stateless CSRF token
  `phil_conversation`, the recipes `Button`, `Badge` and `Textarea` of the kit, and labels in English and French.
- The notification of each message through phil/notifications-bundle, its type declared, its subject named by
  `ConversationSubjects`.
- The Mercure update of each message on each participant's topic, a hub that cannot be reached logged, and the Stimulus
  controller that loads the new messages.
- The export and the erasure of a member's conversations through the contracts of phil/privacy-bundle.
- The ports `CurrentParticipant`, `ParticipantDirectory`, `ContactRelation`, `MessageSealer` and `ConversationReader`.
- The Flex recipe in `recipe/0.1`: the bundle registered, `config/packages/phil_conversation.yaml` and the routes.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
