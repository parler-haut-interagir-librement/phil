# Changelog

All notable changes to `phil/helpdesk-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- Requests that are conversations of phil/conversation-bundle, opened by the requester alone, with a queue, a status
  and an assignee; an agent who answers or is assigned joins the conversation.
- The Symfony Workflow state machine `phil_helpdesk_ticket`: new, open, waiting, resolved, closed; an agent's answer
  makes the request wait for the requester, the requester's answer brings it back; take, resolve, reopen and close by
  hand; a closed request takes no more messages.
- Queues declared by configuration with the roles of their agents, checked through `QueueAccess`; the desk filtered by
  queue, status and assignee, read by pages; assigning, moving between queues.
- Canned replies: Twig templates of the application, written for the request, offered by queue, behind the port
  `CannedReplies` that an adapter to the application's content templates replaces.
- `TicketOpened` and `TicketAssigned`, by identifiers only.
- The DBAL store, the table of `HelpdeskTables` built with `Schema::editor()` and added to the schema the ORM generates,
  moments in UTC; tested on SQLite, MySQL 8.4 and PostgreSQL 17.
- The pages of the requesters and the desk of the agents, with the stateless CSRF token `phil_helpdesk`, the recipes
  `Button`, `Badge`, `Input`, `NativeSelect` and `Textarea` of the kit, and labels in English and French.
- The desk grafted on every EasyAdmin dashboard through `#[AdminRoute]`, in its layout, with `HelpdeskAdmin::menuItem()`.
- The notification of an agent a request is assigned to by someone else, through phil/notifications-bundle.
- The export and the erasure of a member's requests through the contracts of phil/privacy-bundle.
- The Flex recipe in `recipe/0.1`: the bundle registered, one queue and the routes.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
