# Changelog

All notable changes to `phil/reactions-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `Reaction`: a reaction of a member, named by a stable identifier, to a content, named by a `ResourceReference`,
  mapped as the table `phil_reaction` with one row per member, content and code, its moment `reacted_at` a
  `datetime_utc_immutable` column; changing the only one keeps the row;
  `Reaction::member()` refuses an identifier that contains `@`, without writing it in the message;
  `ReactionsException`, `InvalidReaction` and `AlreadyReacted`.
- `ReactionRepository`, whose `of()` gives a member's reactions to a content, oldest first;
  `DoctrineReactionRepository`, in plain queries on the connection, whose refused duplicate closes no entity manager,
  its moments written and read back in UTC; `InMemoryReactionRepository`, for tests, tagged `kernel.reset` and emptied
  between two requests. Counters, the codes a member chose and the first members to react are read
  for many contents at once.
- `ReactionDefinition` and `ReactionGroup`: a code, the translation key of a label, a Unicode emoji or an image with
  its replacement text, private or not, in the bar or in the picker. The port `ReactionSource`,
  `DeclaredReactionSource` for the two sources of the configuration, and `ReactionCatalog`, read once per request, the
  sources in the order the site lists, at most 128 reactions.
- `Audience` and `ReactionVisibility`: who sees the reactions, the counters and the names; the port
  `VisibilityPolicy`, `ConfiguredVisibility`, and `SettingsVisibility` when phil/settings-bundle is there.
- A private reaction has no public counter and no names: only the member who chose it sees it pressed.
- `Reactions`: `react()`, `withdraw()`, of one code or of all, and `eraseContent()`, stating the wanted state rather
  than toggling; the attribute `phil_reactions.react` asked about a `ReactionSubject`; the ports `ReactionAccess`,
  refused by `DenyingReactionAccess` without Security, `CurrentMember` and `ReactionThrottle`; `ReactionRefused`,
  `UnknownReaction` and `TooManyReactions`.
- `ReactionsPerMember` and the option `per_member`: one reaction per member and per content, which a new one
  replaces, or several up to a cap from 1 to 20, each code once; `ReactionLimitReached` beyond it, said in the live
  region and answered `409` by the plain form; a race past the cap keeps the oldest reactions; `ReactionPanel::$full`
  and a sentence after the bar once the cap is reached.
- `ReactionBoard`, `ReactionPanel`, `ReactionItem`, `PickerGroup` and `ReactorsLine`: what a viewer sees of a content,
  with `preload()` and the Twig function `reactions_preload` reading a page of contents in one query for each thing to
  know, kept for one request and reset between two requests of a worker; the port `MemberNames`, and `NoMemberNames`;
  the names of those who reacted written as one list in the language of the page by `format_list` of twig/intl-extra,
  whose extension the bundle registers when TwigExtraBundle does not.
- `ReactionsComponent`, the Live Component `PhilReactions`, whose buttons are those of a plain form; its props signed,
  its live region, its browser event that keeps two components of one content in step.
- `ReactionController` and `ReactionGuard`, with `config/routes.php`: the same reactions without JavaScript, by a POST
  whose address is signed and whose stateless CSRF token is checked; a page that says what stopped the reaction, with
  `Retry-After` when the member has to wait.
- `SymfonyThrottle` and the limiter `phil_reactions` of symfony/rate-limiter, 30 changes a minute per member by
  default.
- The Stimulus controllers `reactions`, keeping the focus of its own controls across a render, never taking it from
  another component of the same content, and refreshing the other components of a content, and `picker`, with its search
  by label without case or accent, the arrow keys, Home, End, Escape and a click elsewhere, both fetched lazily;
  `assets/styles/reactions.css`; their paths written in the `asset_mapper` extension when AssetMapperBundle is
  registered.
- `KitComponents` and `KitSubdirectoryPass`: the recipes `ReactionButton` and `ReactionPicker` of phil/ux-kit or
  phil/ux-kit-shadcn, called where the application installed them, from the components subdirectory of the Symfony UX
  Toolkit or the option `kit_subdirectory`.
- The events `ReactionAdded` and `ReactionRemoved`, identifiers only, also sent on the message bus with `messenger:
  true`.
- `ReactionsPrivacy`: a member's reactions in the export and the erasure of phil/privacy-bundle.
- The option `current_member`, required with Symfony Security, whose container is not built without it;
  `TokenCurrentMember`, named there by a site whose user identifiers are stable, refuses an identifier containing
  `@`.
- `MediaEmojiImages`, `PlainEmojiImages` and the port `EmojiImages`: the images of the site's emojis, by address or as
  media of phil/media-bundle, all asked at once and read with `MediaRepository::getMany()` in one query.
- `PhilReactionsBundle`, configured under `phil_reactions`; the translations in English and French; the benchmark of a
  page of 20 contents, read and drawn as a worker does.
