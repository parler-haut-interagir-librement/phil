# Changelog

All notable changes to `phil/follow-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.0-alpha.

### Added

- `Follows`: a member follows a subject — a type and an identifier — at the level `watching`, `mentions`, `ignoring`
  or `custom`, with a level for each kind of event at the custom level; changes the level, stops following, sees the
  subject, and lists their own follows a page at a time. Following a member tells them with `follow.followed`.
- The inheritance: `SubjectParents` names the parents of a subject, nearest first, up to `inheritance.max_depth`; the
  nearest follow decides, so that ignoring a subject wins over watching its parent.
- `FollowAccess`, asked when a member follows and for each page of followers an activity is sent to; required with
  Symfony Security, `OpenAccess` for an application whose subjects are all public.
- The requests: `FollowApproval` names the approver of a locked subject; following it makes a request, told with
  `follow.requested`, that `FollowRequests` accepts — `follow.accepted` —, rejects or withdraws.
- `Mutuals`, with `mutual.enabled`: mutual relationships beside one-way following, asked and accepted through the same
  requests, joined at once when both ask, each member then following the other unless they chose a level; stopping to
  follow keeps the relationship.
- `AutoFollow`, with `auto_follow.created` and `auto_follow.replied`: following what a member creates or replies to.
- `Activities::publish()` and `FanOutActivity`: an activity kept once by identifier, sent to the followers of its subject
  and parents through `Notifications::send()` of phil/notifications-bundle, a page of `batch_size` members at a time in
  seven queries, each delivery claimed per activity and member so that a retry sends to no one twice, the claims given
  back when the call fails.
- The hold until seen, `email.once_until_seen` and `email.resend_after`: after a `follow.activity`, the next activities
  of the subject are `follow.activity_quiet`, kept to the centre, until the member sees the subject or the delay ends.
- The notification types `follow.activity`, `follow.activity_quiet`, `follow.requested`, `follow.accepted` and
  `follow.followed`, declared in `phil_notifications.types` and translated in English and French; the digest of
  phil/notifications-bundle collects them.
- `Followers`: the followers of a subject and their count, which a member who hides them shows to themselves alone.
- The tables `phil_follows`, `phil_follow_requests`, `phil_follow_mutuals`, `phil_follow_activities`,
  `phil_follow_deliveries`, `phil_follow_unseen` and `phil_follow_visibility`, through DBAL, their instants in
  `datetime_utc_immutable` columns, added to the ORM's schema by `FollowSchemaListener`.
- `PurgeFollowRecords` and the schedule `phil_follow`, with a lock and a state when `lock.factory` and `cache.app`
  exist: the activities past `retention.activity_days`, their claims and the old marks deleted every day.
- `FollowPrivacy`: a member's follows, requests, relationships and choice exported, and everything erased, through the
  contracts of phil/privacy-bundle.
- The templates `follow_button`, `requests`, `followers`, `visibility`, `mutual` and `stopped`, their Twig functions,
  and their forms without JavaScript, checked by a signed address and the stateless CSRF token `phil_follow`.
- The documentation in `doc/`, one file per topic, indexed by the README, with how an application composes a home page.
- The Flex recipe, in `recipe/0.1`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
