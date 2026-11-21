# Changelog

All notable changes to `phil/short-link-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `PhilShortLinkBundle` and its configuration `phil_short_link`: the short link domains, one per locale if needed, the
  home of their root, the codes, the targets, the rate limit, the QR codes, the administration, the patterns of
  visitors and the analytics.
- `SqidCodec`, identifiers in the Sqids format with a configurable alphabet, minimum length and blocklist, bounded
  decoding and a canonical check, with a fuzz target; `CodeGenerator`, codes of random numbers; `DefaultBlocklist`.
- `ShortLink`, its `LinkTerms` and `LinkState`: a code, a target, a redirect status, a query policy, a label, a locale,
  a start, an end, a maximum number of visits, a count of visits of people and a count of visits of machines
  (`robotVisits()`, the column `robot_visits`), without any visitor data.
- `VisitorClass` and the port `VisitorClassifier`, with `KnownVisitorClassifier`: each visit told a person, a robot, a
  prefetch (`Sec-Purpose`, `Purpose`, `X-Purpose`, `X-Moz`) or a link preview of a messaging service, from known
  patterns of the `User-Agent` that the configuration `visitors` extends, bounded, with a fuzz target and a benchmark;
  only the class is kept, on `ShortLinkVisit`, and only people count towards a link's visits and its maximum.
- `ShortLinks` and `ShortLinkDraft`: creation, change, enabling and removal through the same checks — absolute targets
  read by phil/url, allowed hosts, no link to a short domain, an optional check through phil/safe-http, drawn or chosen
  codes, locales with a domain, and a rate limit per author.
- `ShortLinkResolver` on the resolver of phil/redirects-bundle, and `ShortLinkListener`, which answers the short link
  domains before the routing with a redirect, a `404` or a `410`, counts each visit atomically, and tells the
  `VisitRecorder`s after the response.
- `DoctrineShortLinkRepository`, its atomic count of a visit, and its tests on SQLite, MySQL and PostgreSQL.
- The administration — list, search, form, enabling, removal, QR codes in SVG and PNG — in English and French, with the
  class names of the PHIL UX kit, and `ShortLinkType`.
- `RateLimiterCreationThrottle`, `SafeHttpTargetCheck` and `AnalyticsVisitRecorder`, which counts the visits of people.
- The Flex recipe in `recipe/0.1`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
