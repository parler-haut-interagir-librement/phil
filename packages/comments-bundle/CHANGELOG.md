# Changelog

All notable changes to `phil/comments-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Anchor`: where a passage is — a block, the path of a field in its data and character offsets in the field's text,
  or a plain text — with the passage itself and up to 32 characters of context on each side; `InvalidComment` for
  what an anchor, a thread or a comment cannot be made of, and `CommentsException` for every exception of the bundle.
- `Thread` and `Comment`: a discussion about a passage or about the whole page, open or resolved, attached or orphaned,
  with a suggested replacement — on a passage only — pending, accepted or rejected, and its plain-text comments; authors
  and actors named by stable identifiers, an identifier holding `@` refused through `Member`; mapped for Doctrine as the
  tables `phil_comment_thread` and `phil_comment`; `Suggestion`, `ThreadStatus` and `SuggestionState`.
- `Passage` and `InlineHtml`: the texts where passages are searched, the text of inline HTML as a browser reads it,
  and a passage of it replaced with every tag kept.
- `Reanchoring`: a passage found again by its exact text in changed texts — its field first, then its block, then
  another occurrence of the same words; then by its context; then by its old place, deterministically —, or gone;
  contexts compared byte by byte, so that a field where the passage occurs everywhere costs a time bounded by its
  length; `AnchoringBench` and the fuzz target `reanchoring`.
- `Comments`: threads opened on a passage or, with `openOnPage`, on the whole page, answered, edited, deleted,
  resolved, reopened, their suggestions accepted, rejected or applied to a UJO document, and moved with their passages
  by `reanchor`, `reanchorDocument` and `reanchorText`, which leave the page's threads alone; `CommentWritten` and
  `CommentDeleted`, told to the event dispatcher when one is given;
  `Discussion`; `CommentPolicy` and `DefaultCommentPolicy`; `AuthorProvider` and `AuthorDirectory`; `NotFound` and
  `CommentRefused`.
- `UjoPassages`: the texts of a UJO document by block and path, inline HTML or plain source, and a passage replaced
  in it.
- `ThreadRepository` and `CommentRepository`; `DoctrineThreadRepository` and `DoctrineCommentRepository`, on the entity
  manager; `InMemoryThreadRepository` and `InMemoryCommentRepository`, for tests, tagged `kernel.reset` and emptied
  between requests.
- `PhilCommentsBundle`, configured under `phil_comments`: `store`, `entity_manager`, `author_provider`, `policy`,
  `author_directory`, `aria_roles` and `kit_subdirectory`; the tables mapped for Doctrine; the controller's CSRF token
  added to the stateless ones of FrameworkBundle, and `CsrfProtectionPass`, which stops the container with a message
  when `framework.csrf_protection` is false; the controller and the stylesheet mapped in the extension of
  AssetMapperBundle when it is enabled.
- `CommentsController`, `CommentsRequest`, `DiscussionJson`, `Routes` and the routes in `config/routes.php`: list,
  open, reply, resolve, reopen, accept, reject, edit and delete, each for the controller's own header, the browser's
  `Sec-Fetch-Site`, Symfony's stateless CSRF check of the token `phil_comments` on each `POST` (the list, a `GET`,
  carries none), an author and the application's policy.
- `ClientConfig`: the controller's configuration for one resource and one viewer, with the name of its CSRF cookie,
  whether the panel offers to comment on the whole page and, with `phil/mentions-bundle` enabled, the address of the
  member search, and whether the roles of WAI-ARIA 1.3 are written; `open` with no anchor, for a thread on the whole
  page.
- `PageCommentsComponent`, the Twig component `PhilComments:Page`: the comments on a whole page drawn by the server
  with the recipes of the PHIL UX kit — `Alert`, `Badge`, `Button`, `Card`, `Field`, `Textarea` —, found under the
  Toolkit's subdirectory (`KitComponents`, `KitSubdirectoryPass`); `PageController`, `PageForm` and the route in
  `config/page_routes.php`: plain forms that open, reply, edit, delete, resolve and reopen without JavaScript, at a
  signed address, under the stateless CSRF check and the application's policy, back to the page or to
  `stopped.html.twig`, which keeps the text sent; their labels in English and French, domain `phil_comments`.
- The `comments` Stimulus controller: a selection in one field of the block editor offers to comment or suggest,
  threads listed beside the editor in the order of their passages, passages highlighted through the CSS Custom
  Highlight API and followed as the writer types, orphaned threads said so, the comments on the whole page and their
  form, on a page with or without an editor, replies, resolve and reopen, edit and delete, suggestions accepted
  into the editor, labels in English and French, a new CSRF token with each `POST`, the roles `comment` and
  `suggestion` of WAI-ARIA 1.3 and `aria-details` from a passage to its comments when asked; fetched lazily; its
  modules — passages,
  requests, the double-submitted token, the panel's elements, labels — and
  the tests of the pure ones under Node's test runner.
- The text areas of the panel and of the page with the `@` autocomplete of `phil/mentions-bundle`, when its member
  search is known.
- The stylesheet `comments.css`, on custom properties that follow PHIL UX or the page's own colours.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
