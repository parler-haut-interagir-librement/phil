# PHIL

**Proclaim Honestly, Interact Liberally: open PHP libraries and Symfony bundles for websites that publish, take part
and protect their visitors.**

PHIL is a set of small `phil/*` packages, each doing one thing: a library in plain PHP, usually paired with a Symfony
bundle that wires it into forms, validation, Twig, Doctrine and configuration. They are typed for PHPStan at its maximum
level and tested down to the mutation of every line of their domain.

This repository is the **monorepo** where every package is developed, tested and documented together. Each package is
then split into its own **read-only** repository, from which Composer installs it through Packagist.

## Packages

### Enums

| Package | Description |
|---|---|
| [`phil/enums`](https://github.com/parler-haut-interagir-librement/enums) | Traits and attributes that make PHP enums easier to list, compare, describe and group into sets. |
| [`phil/enums-bundle`](https://github.com/parler-haut-interagir-librement/enums-bundle) | Your PHP enums in Symfony forms, translations, Twig, validation, Doctrine and API contracts. |

### Architecture

| Package | Description |
|---|---|
| [`phil/resource`](https://github.com/parler-haut-interagir-librement/resource) | Point at any record of your application by its kind and identifier, such as `page:42`. |
| [`phil/ddd-bundle`](https://github.com/parler-haut-interagir-librement/ddd-bundle) | The few types every bounded context shares: identifiers, domain events, errors, command and query buses. |
| [`phil/references-bundle`](https://github.com/parler-haut-interagir-librement/references-bundle) | Know where each resource is used, and refuse to delete one that something still needs. |
| [`phil/trash-bundle`](https://github.com/parler-haut-interagir-librement/trash-bundle) | A trash for deleted pages and other resources: restore them, or purge them after a set time. |
| [`phil/tenancy-bundle`](https://github.com/parler-haut-interagir-librement/tenancy-bundle) | The active tenant of a multi-tenant application: resolved from the account or the host, kept by a fail-closed Doctrine filter, carried across Messenger, sessions, mail and caches. |
| [`phil/tenancy-database-bundle`](https://github.com/parler-haut-interagir-librement/tenancy-database-bundle) | A database for each tenant, or the shared one: the connection follows the tenant, migrations run per database, and a tenant moves from one database to another, verified and reversible. |
| [`phil/settings-bundle`](https://github.com/parler-haut-interagir-librement/settings-bundle) | Settings people change themselves: declared by packages, read typed with their default, a preferences centre and site settings, and display preferences applied to the page. |
| [`phil/outbox`](https://github.com/parler-haut-interagir-librement/outbox) | A transactional outbox: events recorded with the write they describe, relayed at least once, in order per key, with retries and dead records. |
| [`phil/outbox-bundle`](https://github.com/parler-haut-interagir-librement/outbox-bundle) | The outbox in Symfony: a table of the Doctrine DBAL connection, a relay command safe in worker mode, a scheduled trigger and an optional bridge to Messenger. |

### Text and documents

| Package | Description |
|---|---|
| [`phil/slug`](https://github.com/parler-haut-interagir-librement/slug) | URL slugs made from titles and names, as a type that accepts nothing else. |
| [`phil/slug-bundle`](https://github.com/parler-haut-interagir-librement/slug-bundle) | Named sluggers declared in the configuration, autowired by name and usable in Twig. |
| [`phil/ujo`](https://github.com/parler-haut-interagir-librement/ujo) | UJO (Universal JSON Output) documents as values you can trust: read strictly, checked block by block, rendered as safe HTML. |
| [`phil/ujo-bundle`](https://github.com/parler-haut-interagir-librement/ujo-bundle) | UJO (Universal JSON Output) documents in a Symfony application: a field that checks them, a constraint, and safe HTML in Twig. |
| [`phil/typography`](https://github.com/parler-haut-interagir-librement/typography) | Real quotation marks, dashes, French spaces and soft hyphens in your HTML, in every language, without touching the markup. |
| [`phil/typography-bundle`](https://github.com/parler-haut-interagir-librement/typography-bundle) | Typographic quotes, dashes, French spaces and soft hyphens everywhere a Symfony application prints text. |
| [`phil/text-diff`](https://github.com/parler-haut-interagir-librement/text-diff) | What changed between two texts, word by word, paragraph by paragraph, or block by block — shown in HTML or listed. |
| [`phil/highlight`](https://github.com/parler-haut-interagir-librement/highlight) | Code in 24 languages coloured on the server, as plain HTML spans any stylesheet can theme. |
| [`phil/highlight-bundle`](https://github.com/parler-haut-interagir-librement/highlight-bundle) | Code coloured on the server in a Symfony application: in UJO documents, in templates, with a theme ready to link. |
| [`phil/css-sanitizer`](https://github.com/parler-haut-interagir-librement/css-sanitizer) | Let people style their pages without letting their CSS run code, load what it should not, or leave its frame. |
| [`phil/qr-code`](https://github.com/parler-haut-interagir-librement/qr-code) | QR codes drawn as SVG or PNG by PHP alone, with no service and no image library. |
| [`phil/markup`](https://github.com/parler-haut-interagir-librement/markup) | Markdown, BBCode, MediaWiki, DokuWiki, Creole, UJO, HTML or plain text, turned into HTML that is safe to show. |
| [`phil/markup-bundle`](https://github.com/parler-haut-interagir-librement/markup-bundle) | Markdown, BBCode, the wikis, UJO and user HTML rendered safely in Twig, converted into UJO, with phil/markup as Symfony services. |
| [`phil/timed-text`](https://github.com/parler-haut-interagir-librement/timed-text) | Subtitles, captions, chapters and transcripts read and written as WebVTT, SRT and Podcasting 2.0, in integer milliseconds, shifted, merged and split. |
| [`phil/svg-sanitizer`](https://github.com/parler-haut-interagir-librement/svg-sanitizer) | Let people upload and paste SVG images without letting them run code, load what they should not, or embed another document. |
| [`phil/qr-code-bundle`](https://github.com/parler-haut-interagir-librement/qr-code-bundle) | QR codes in a Symfony application: in Twig, in an accessible component, at a cached address, in UJO documents and in PDF documents. |
| [`phil/block-editor-bundle`](https://github.com/parler-haut-interagir-librement/block-editor-bundle) | A block editor for Symfony forms, in Stimulus controllers, that writes UJO (Universal JSON Output) documents checked on the server. |
| [`phil/edit-lock-bundle`](https://github.com/parler-haut-interagir-librement/edit-lock-bundle) | One writer per block: edit leases, presence and block saves pushed through Mercure, for Symfony applications. |
| [`phil/footnotes-sidenotes`](https://github.com/parler-haut-interagir-librement/footnotes-sidenotes) | Footnotes and margin notes for UJO (Universal JSON Output) documents, on screen and in print. |
| [`phil/text-diff-bundle`](https://github.com/parler-haut-interagir-librement/text-diff-bundle) | What changed between two versions, shown in a template and walked through change by change. |
| [`phil/svg-chart`](https://github.com/parler-haut-interagir-librement/svg-chart) | Charts drawn as SVG on the server, from the configuration a chart editor already writes. |
| [`phil/content-warnings`](https://github.com/parler-haut-interagir-librement/content-warnings) | Content warnings for games, books, articles and events, and the themes each reader folds or hides. |
| [`phil/content-warnings-bundle`](https://github.com/parler-haut-interagir-librement/content-warnings-bundle) | Content warnings in Symfony applications: a catalogue from configuration, warnings on any entity, and content folded or hidden as each reader chooses. |
| [`phil/taxonomy-bundle`](https://github.com/parler-haut-interagir-librement/taxonomy-bundle) | Tags and a translated category tree for any kind of resource, with form types to edit them. |
| [`phil/epub`](https://github.com/parler-haut-interagir-librement/epub) | EPUB 3 books from purified HTML or UJO documents: navigation, accessibility metadata, fonts, media overlays and fixed layout. |
| [`phil/document-import`](https://github.com/parler-haut-interagir-librement/document-import) | DOCX, ODT, RTF, PDF text, Markdown and HTML turned into UJO (Universal JSON Output) documents, with a report of what was left out; UJO exported as DOCX and ODT. |
| [`phil/document-import-bundle`](https://github.com/parler-haut-interagir-librement/document-import-bundle) | Word, OpenDocument, RTF and PDF files imported into UJO documents in Symfony: scanned first, limits and style map configured, images kept in the media library. |
| [`phil/braille-export`](https://github.com/parler-haut-interagir-librement/braille-export) | Documents exported to braille: uncontracted French and English in PHP, contracted braille through a translator, braille page layout, and BRF, PEF and Unicode. |
| [`phil/braille-export-liblouis`](https://github.com/parler-haut-interagir-librement/braille-export-liblouis) | Contracted and uncontracted braille in every language liblouis has tables for, through its lou_translate program, as the translator of phil/braille-export. |
| [`phil/epub-bundle`](https://github.com/parler-haut-interagir-librement/epub-bundle) | Books built by sources registered by attribute, written now or by a Messenger message, with their fonts and images. |
| [`phil/latex-mathml`](https://github.com/parler-haut-interagir-librement/latex-mathml) | LaTeX formulas converted to MathML Core, any other command refused with its position, length, depth and expansion bounded. |
| [`phil/svg-blocks`](https://github.com/parler-haut-interagir-librement/svg-blocks) | The math, mermaid, chart and QR code blocks of UJO documents drawn once when saved, as signed SVG or MathML. |
| [`phil/number-words`](https://github.com/parler-haut-interagir-librement/number-words) | Numbers spelled out in words over ICU: cardinals, ordinals, gender and amounts of money, corrected for six languages. |
| [`phil/citations`](https://github.com/parler-haut-interagir-librement/citations) | References kept as CSL-JSON, BibTeX and RIS import, and author-date, notes and numeric styles in English and French. |
| [`phil/slides`](https://github.com/parler-haut-interagir-librement/slides) | Slides in Symfony from a UJO document or Markdown: speaker notes kept from the audience, fragments, a presenter view behind an optional protected route, and PDF export. |
| [`phil/profile-editorial`](https://github.com/parler-haut-interagir-librement/profile-editorial) | One require for the packages that publish editorial content: safe markup, citations, footnotes and sidenotes, content warnings and schema.org data. |

### Forms and protection

| Package | Description |
|---|---|
| [`phil/spam-protection`](https://github.com/parler-haut-interagir-librement/spam-protection) | Spot spam in a form submission without a captcha, in plain PHP. |
| [`phil/spam-protection-bundle`](https://github.com/parler-haut-interagir-librement/spam-protection-bundle) | Keep spam out of your Symfony forms without a captcha, a third party or an identifier. |
| [`phil/bot-detection-bundle`](https://github.com/parler-haut-interagir-librement/bot-detection-bundle) | Tell a robot from a human in the browser, and let only that one word out. |
| [`phil/contact-form-bundle`](https://github.com/parler-haut-interagir-librement/contact-form-bundle) | A "write to us" page for Symfony, protected from spam without a captcha. |
| [`phil/passwords`](https://github.com/parler-haut-interagir-librement/passwords) | Tell people how guessable their password is, refuse the ones already leaked, and give them good ones. |
| [`phil/upload-scan`](https://github.com/parler-haut-interagir-librement/upload-scan) | Every uploaded file through an antivirus before anyone else can open it. |
| [`phil/forms-autosave`](https://github.com/parler-haut-interagir-librement/forms-autosave) | A form's input kept while it is filled, restored after a reload, and forgotten once it is sent. |
| [`phil/form-logic`](https://github.com/parler-haut-interagir-librement/form-logic) | Typed conditional logic for forms, evaluated alike in PHP and in a Stimulus controller with the server as judge, as a strict JsonLogic subset. |
| [`phil/forms-bundle`](https://github.com/parler-haut-interagir-librement/forms-bundle) | Forms defined in the database: a page for each, protected against spam, questions asked under conditions, entries kept no longer than needed, sent by email or to a signed webhook. |
| [`phil/upload-scan-bundle`](https://github.com/parler-haut-interagir-librement/upload-scan-bundle) | Uploads through the antivirus in Symfony: a constraint, a quarantine outside the web root, an admin page, rescans on a worker and a health check. |
| [`phil/profile-secure-input`](https://github.com/parler-haut-interagir-librement/profile-secure-input) | One require for the packages that guard what users send: addresses, outgoing fetches, request rates, form spam and uploaded files. |

### Money, phone numbers and dates

| Package | Description |
|---|---|
| [`phil/money`](https://github.com/parler-haut-interagir-librement/money) | Amounts of money as exact integers in a known currency, with no float anywhere. |
| [`phil/money-bundle`](https://github.com/parler-haut-interagir-librement/money-bundle) | Money amounts in Symfony forms, validation, Twig templates and Doctrine entities, without floats. |
| [`phil/phone`](https://github.com/parler-haut-interagir-librement/phone) | Phone numbers read as people type them, checked against their country's plan, and formatted. |
| [`phil/phone-bundle`](https://github.com/parler-haut-interagir-librement/phone-bundle) | A phone number field, a validation constraint, a column type and Twig filters for Symfony. |
| [`phil/recurrence`](https://github.com/parler-haut-interagir-librement/recurrence) | Repeating dates from an RFC 5545 rule: read it, check it, write it, list its occurrences. |
| [`phil/recurrence-bundle`](https://github.com/parler-haut-interagir-librement/recurrence-bundle) | A form for repeating events, a column to store the rule, and the rule in words. |
| [`phil/tax`](https://github.com/parler-haut-interagir-librement/tax) | Several taxes on one sale, each computed on the same base and itemised to the cent. |
| [`phil/opening-hours`](https://github.com/parler-haut-interagir-librement/opening-hours) | A place's opening hours, and whether it is open right now. |
| [`phil/opening-hours-bundle`](https://github.com/parler-haut-interagir-librement/opening-hours-bundle) | An opening hours form, a JSON column and localised hours in Twig, for Symfony. |
| [`phil/almanac`](https://github.com/parler-haut-interagir-librement/almanac) | Dates that are computed: Easter, equinoxes, phases of the Moon, eclipses, sunrise and sunset, and the rules of holidays. |
| [`phil/almanac-bundle`](https://github.com/parler-haut-interagir-librement/almanac-bundle) | Computed dates in Doctrine, forms and Twig, with the places whose sun and sky they follow. |
| [`phil/calendar-sync`](https://github.com/parler-haut-interagir-librement/calendar-sync) | Busy times read from iCalendar feeds, CalDAV, Google Calendar and Microsoft Graph, and a site's events written to them. |

### Links, addresses and places

| Package | Description |
|---|---|
| [`phil/url`](https://github.com/parler-haut-interagir-librement/url) | Web addresses as normalised values, with their kind detected: Facebook, YouTube, Mastodon, a website. |
| [`phil/url-bundle`](https://github.com/parler-haut-interagir-librement/url-bundle) | Link fields that detect their kind, a validation constraint, a column type and Twig filters. |
| [`phil/instant-messenger`](https://github.com/parler-haut-interagir-librement/instant-messenger) | Messaging handles read, shown and linked per service: WhatsApp, Telegram, Signal, Matrix and more. |
| [`phil/instant-messenger-bundle`](https://github.com/parler-haut-interagir-librement/instant-messenger-bundle) | A messaging handle field with its service, a validation constraint and Twig filters for Symfony. |
| [`phil/address`](https://github.com/parler-haut-interagir-librement/address) | Postal addresses checked and written the way each country writes them. |
| [`phil/address-bundle`](https://github.com/parler-haut-interagir-librement/address-bundle) | An address form for Symfony whose fields follow the country the person picks. |
| [`phil/geo`](https://github.com/parler-haut-interagir-librement/geo) | Coordinates, distances and bounding boxes as PHP values, and geocoding through the provider you pick. |
| [`phil/map-bundle`](https://github.com/parler-haut-interagir-librement/map-bundle) | Maps on Symfony UX Map and Leaflet that no tile server tracks: proxied or self-hosted PMTiles tiles, places as markers, privacy-grid areas, booth floor plans. |
| [`phil/geo-bundle`](https://github.com/parler-haut-interagir-librement/geo-bundle) | Stored coordinates, distances in Twig, configured geocoders and nearest-first lists for Symfony applications. |
| [`phil/contact`](https://github.com/parler-haut-interagir-librement/contact) | Contact cards: names ordered by their culture, emails, phone lines and handles, written as vCard, JSContact and JSON-LD. |
| [`phil/contact-bundle`](https://github.com/parler-haut-interagir-librement/contact-bundle) | A contact card form, a JSON column, vCard and JSContact downloads, and person names in Twig, for Symfony. |
| [`phil/short-link-bundle`](https://github.com/parler-haut-interagir-librement/short-link-bundle) | Short links on your own domains, with QR codes, dates and visit limits, and visits counted without any visitor data. |

### Site and navigation

| Package | Description |
|---|---|
| [`phil/menu-bundle`](https://github.com/parler-haut-interagir-librement/menu-bundle) | Navigation menus and breadcrumbs for Symfony, with the visitor's current page marked. |
| [`phil/redirects-bundle`](https://github.com/parler-haut-interagir-librement/redirects-bundle) | Redirects for Symfony, managed from an administration and resolved only when nothing else answered. |
| [`phil/sitemap`](https://github.com/parler-haut-interagir-librement/sitemap) | Sitemap XML for search engines, built from checked pages and cut to the protocol's limits. |
| [`phil/sitemap-bundle`](https://github.com/parler-haut-interagir-librement/sitemap-bundle) | `/sitemap.xml` for a Symfony site, from route options, URL providers and an event. |
| [`phil/structured-data`](https://github.com/parler-haut-interagir-librement/structured-data) | The schema.org graph of a page as values: nodes from every part of a site, merged by `@id`, checked, and printed as one JSON-LD script element. |
| [`phil/well-known-bundle`](https://github.com/parler-haut-interagir-librement/well-known-bundle) | The `/.well-known/` addresses of a Symfony site, each turned on in its configuration. |
| [`phil/http-cache-tags-bundle`](https://github.com/parler-haut-interagir-librement/http-cache-tags-bundle) | Cache whole pages in Varnish or a CDN, and purge exactly the ones an edit changes. |
| [`phil/structured-data-bundle`](https://github.com/parler-haut-interagir-librement/structured-data-bundle) | The schema.org graph of each page in Symfony, printed as one JSON-LD script element, never for a page kept out of search engines. |
| [`phil/social-cards`](https://github.com/parler-haut-interagir-librement/social-cards) | Open Graph, X and fediverse tags, share cards drawn from a template, and the tags of linked pages read and checked. |
| [`phil/social-cards-bundle`](https://github.com/parler-haut-interagir-librement/social-cards-bundle) | Share tags on each page, share cards drawn by a worker, and safe previews of the links members paste. |
| [`phil/embed-bundle`](https://github.com/parler-haut-interagir-librement/embed-bundle) | Features other sites embed: a token signed for one origin, frame-ancestors for that origin alone, and a plain link without JavaScript. |
| [`phil/pwa-bundle`](https://github.com/parler-haut-interagir-librement/pwa-bundle) | An installable site that reads offline, without Workbox: a manifest, a service worker, an offline page and a second screen. |

### Community and events

| Package | Description |
|---|---|
| [`phil/poll`](https://github.com/parler-haut-interagir-librement/poll) | A voting engine: ballots checked against the rules of their poll, and counted into standings. |
| [`phil/reservation`](https://github.com/parler-haut-interagir-librement/reservation) | The core of bookings: a resource, its slots, its rules, its conflicts and its reminders. |
| [`phil/calendar-bundle`](https://github.com/parler-haut-interagir-librement/calendar-bundle) | Events with recurrence, venues and hosts in Symfony: occurrences expanded within a budget, month to poster views in a Live component, iCalendar, private feeds. |
| [`phil/reservation-bundle`](https://github.com/parler-haut-interagir-librement/reservation-bundle) | Bookable resources in Symfony: reservations held, booked, moved and cancelled without double booking, free slots, expiring holds and reminders via Messenger. |
| [`phil/checkin`](https://github.com/parler-haut-interagir-librement/checkin) | The check-in of an event or a venue: a code read at a door, decided at once, recorded once, online or offline. |
| [`phil/listings`](https://github.com/parler-haut-interagir-librement/listings) | The core of listings: a classified ad, a job offer, a search for partners — written, moderated, answered and expired by the same rules. |
| [`phil/open-badges`](https://github.com/parler-haut-interagir-librement/open-badges) | Open Badges 3.0 that anyone can verify: issued, signed, baked into the image, revoked, endorsed and gathered in a learner record. |
| [`phil/directory-bundle`](https://github.com/parler-haut-interagir-librement/directory-bundle) | A directory of listings with their history, editors and relations, each found as its author decides. |
| [`phil/checkin-bundle`](https://github.com/parler-haut-interagir-librement/checkin-bundle) | The check-in desk in Symfony: scan, manifest and synchronisation endpoints, and a desk that keeps checking in offline. |
| [`phil/comments-bundle`](https://github.com/parler-haut-interagir-librement/comments-bundle) | Comments on a whole page or on a passage of a text, with anchors that follow edits and suggested replacements. |
| [`phil/community-notes`](https://github.com/parler-haut-interagir-librement/community-notes) | Community notes written by qualified members and published by an explainable cross-agreement rule. |
| [`phil/poll-bundle`](https://github.com/parler-haut-interagir-librement/poll-bundle) | Polls and ballots in Symfony: anonymous ballots changed by receipt, and the single transferable vote by Meek's method with its rounds. |
| [`phil/stockroom`](https://github.com/parler-haut-interagir-librement/stockroom) | A props stockroom: nested containers, items counted or tracked one by one, a journal of every movement, and load lists checked at the return. |
| [`phil/notifications-bundle`](https://github.com/parler-haut-interagir-librement/notifications-bundle) | Notifications on Symfony Notifier, delivered to the channels each recipient chose: a notification centre with toasts, email and its digest, Web Push, Slack or Discord. |
| [`phil/conversation-bundle`](https://github.com/parler-haut-interagir-librement/conversation-bundle) | Private messages in Symfony, sealed at rest: direct and group conversations, who may write to whom, blocks, read and unread, optional notifications and Mercure. |
| [`phil/helpdesk-bundle`](https://github.com/parler-haut-interagir-librement/helpdesk-bundle) | A help desk in Symfony where each request is a private conversation: queues worked by role, statuses moved by Symfony Workflow, assignees and canned replies. |
| [`phil/mentions-bundle`](https://github.com/parler-haut-interagir-librement/mentions-bundle) | @mentions of members in plain text, Markdown and UJO documents, with only the newly mentioned members who may see the content notified, and an autocomplete. |
| [`phil/onboarding-bundle`](https://github.com/parler-haut-interagir-librement/onboarding-bundle) | The welcome of a new member: flows of ordered steps, each person's progress, and an accessible checklist and guided tour. |
| [`phil/open-badges-bundle`](https://github.com/parler-haut-interagir-librement/open-badges-bundle) | Open Badges in Symfony: the issuer and its key from the configuration, its profile, keys and status lists served, and a verification page for tokens, documents and baked images. |
| [`phil/certificates-bundle`](https://github.com/parler-haut-interagir-librement/certificates-bundle) | Certificates of completion on Open Badges: courses and pass criteria, a PDF certificate with a verification QR code and the signed credential, a public register and revocation. |
| [`phil/stockroom-bundle`](https://github.com/parler-haut-interagir-librement/stockroom-bundle) | The props stockroom in Symfony: containers, items, journal and load lists in Doctrine DBAL tables, moves in a transaction that locks the items. |
| [`phil/moderation-bundle`](https://github.com/parler-haut-interagir-librement/moderation-bundle) | One report queue for posts, comments, reactions, listings, messages and profiles: roles, decision history, a statement of reasons to authors and a public journal without names. |
| [`phil/newsletter-bundle`](https://github.com/parler-haut-interagir-librement/newsletter-bundle) | A site's newsletter: lists, double opt-in, campaigns in Markdown, sending at the pace of sender profiles, one-click unsubscription, a preference centre and bounces read back. |
| [`phil/reactions-bundle`](https://github.com/parler-haut-interagir-librement/reactions-bundle) | Reactions to any content: Unicode emojis and the site's own, an accessible picker, a Live Component that also works as a plain form, and counters as each site sets. |
| [`phil/avatar`](https://github.com/parler-haut-interagir-librement/avatar) | Avatars for members without a photo: SVG drawn from an opaque seed, never an email, as initials with WCAG AA contrast, shapes or pixel grids, deterministic. |
| [`phil/habits`](https://github.com/parler-haut-interagir-librement/habits) | Habit tracking that does not punish: schedules in the member's time zone, check-ins done, skipped or partial, forgiving streaks, a strength score, DBAL storage. |
| [`phil/assignment`](https://github.com/parler-haut-interagir-librement/assignment) | Places people by ranked preferences (game tables, workshops, rooms, roles): ranked rounds or a min-cost optimum, seeded, reproducible, each placement explained. |
| [`phil/proposal-review`](https://github.com/parler-haut-interagir-librement/proposal-review) | Jury review for calls for papers and contests: dated phases, weighted criteria, blind review with conflicts, batch decisions, pairwise votes, DBAL storage. |
| [`phil/event-weather`](https://github.com/parler-haut-interagir-librement/event-weather) | The weather forecast of an outdoor event's place and time: Environment Canada with its alerts in Canada, MET Norway elsewhere, each provider credited. |
| [`phil/library-bundle`](https://github.com/parler-haut-interagir-librement/library-bundle) | Loans of a game or book library in Symfony: a catalogue searched without JavaScript, copies lent, reserved, renewed and returned, overdue notices by Messenger. |

### Print and products

| Package | Description |
|---|---|
| [`phil/fonts`](https://github.com/parler-haut-interagir-librement/fonts) | What a font file says about itself — its names, its weight, its embedding licence — and the `@font-face` rule to serve it. |
| [`phil/print-preflight`](https://github.com/parler-haut-interagir-librement/print-preflight) | What a printer would refuse, before the file reaches it. |
| [`phil/print-compositor`](https://github.com/parler-haut-interagir-librement/print-compositor) | The mockups a shop shows and the print files a printer receives, drawn from the same layers. |
| [`phil/commerce-product-template`](https://github.com/parler-haut-interagir-librement/commerce-product-template) | One template and one design give every product of a catalogue: each SKU, its price, its channels and its artworks. |
| [`phil/paged-media`](https://github.com/parler-haut-interagir-librement/paged-media) | PDF documents behind one API: a native writer with no dependency, form fields, printed templates, and a Gotenberg client. |
| [`phil/paged-media-bundle`](https://github.com/parler-haut-interagir-librement/paged-media-bundle) | The native PDF writer and Gotenberg behind one service, document templates by attribute, and asynchronous rendering by Messenger. |
| [`phil/paged-media-weasyprint`](https://github.com/parler-haut-interagir-librement/paged-media-weasyprint) | HTML and CSS laid out as a PDF by WeasyPrint, with no access to the network and files from one directory only. |
| [`phil/fonts-bundle`](https://github.com/parler-haut-interagir-librement/fonts-bundle) | A directory of font files read into a catalogue, @font-face CSS, and a licence register checked by a command. |
| [`phil/print-preflight-bundle`](https://github.com/parler-haut-interagir-librement/print-preflight-bundle) | Printer profiles from the configuration, checks added by attribute, and documents checked before they are printed. |
| [`phil/commerce-bundle`](https://github.com/parler-haut-interagir-librement/commerce-bundle) | The core of commerce in Symfony: orders and adjustments, channels, taxes per line, payments with verified notifications, gapless receipts and credit notes with their PDF invoice. |
| [`phil/commerce-catalog-bundle`](https://github.com/parler-haut-interagir-librement/commerce-catalog-bundle) | Products, variants and price books for a Symfony shop on phil/commerce-bundle: families, composites, translated names, categories, pay what you want. |
| [`phil/commerce-product-template-bundle`](https://github.com/parler-haut-interagir-librement/commerce-product-template-bundle) | Every product of a design on a template generated into the Symfony catalogue, with its mockups, its print files and the production orders for the printer. |
| [`phil/commerce-promotions-bundle`](https://github.com/parler-haut-interagir-librement/commerce-promotions-bundle) | Promotions for a Symfony shop on phil/commerce-bundle: a share or amount off, N for X, bundle prices, free shipping, codes in batches, uses per customer. |
| [`phil/commerce-logistics-bundle`](https://github.com/parler-haut-interagir-librement/commerce-logistics-bundle) | Stock, shipments and delivery for a Symfony shop on phil/commerce-bundle: every movement journaled, reservations, carriers' rates and labels, convention stock. |
| [`phil/commerce-pos-bundle`](https://github.com/parler-haut-interagir-librement/commerce-pos-bundle) | A shop register in Symfony on phil/commerce-bundle: tills, cash sessions and end-of-day reports, connected terminals, layaways, and sales rung up offline. |
| [`phil/commerce-stored-value-bundle`](https://github.com/parler-haut-interagir-librement/commerce-stored-value-bundle) | Gift cards and store credit for a Symfony shop on phil/commerce-bundle: sold, issued, loaded and spent as payments, with a printed card. |
| [`phil/commerce-returns-bundle`](https://github.com/parler-haut-interagir-librement/commerce-returns-bundle) | Returns for a Symfony shop on phil/commerce-bundle: requests authorized, received, inspected, items restocked, refunds, store credit, exchanges, credit notes. |
| [`phil/commerce-subscription-bundle`](https://github.com/parler-haut-interagir-librement/commerce-subscription-bundle) | Subscriptions in Symfony on phil/commerce-bundle: tiers, billing periods, trials, renewal and dunning, proration, gifts, seats, the features each tier unlocks. |
| [`phil/commerce-wholesale-bundle`](https://github.com/parler-haut-interagir-librement/commerce-wholesale-bundle) | Wholesale for a Symfony shop on phil/commerce-bundle: customer groups with their prices and minimums, quantity rules per SKU, sales on credit with net terms. |
| [`phil/commerce-syndication-bundle`](https://github.com/parler-haut-interagir-librement/commerce-syndication-bundle) | A supplier's catalogue sold by other shops, in Symfony: signed prices and stock fed to resellers, orders forwarded and shipped, returns credited, one ledger. |
| [`phil/product-feeds`](https://github.com/parler-haut-interagir-librement/product-feeds) | Product feeds for comparison sites and marketplaces, written in constant memory as RSS with product data or as TSV, and swapped into place whole. |
| [`phil/licensing-bundle`](https://github.com/parler-haut-interagir-librement/licensing-bundle) | The licenses of works and products: what each requires, attribution pages in HTML, UJO or JSON-LD, and a compliance check before export or print. |

### Content and search

| Package | Description |
|---|---|
| [`phil/content-bundle`](https://github.com/parler-haut-interagir-librement/content-bundle) | Content by language, stage and version: drafts published through a workflow, bodies published as UJO, routes that keep old slugs, and an administration. |
| [`phil/locale-dimensions`](https://github.com/parler-haut-interagir-librement/locale-dimensions) | Languages, versions and the fallback chains between languages, as plain value objects. |
| [`phil/publishing`](https://github.com/parler-haut-interagir-librement/publishing) | The events a page sends when it goes online or offline, for the listeners that follow it. |
| [`phil/scheduled-transitions-bundle`](https://github.com/parler-haut-interagir-librement/scheduled-transitions-bundle) | Publish or unpublish at a date: workflow transitions planned and run by the Symfony Scheduler. |
| [`phil/doctrine-behaviours`](https://github.com/parler-haut-interagir-librement/doctrine-behaviours) | Doctrine behaviours: timestamps, authors, soft delete, trees, slugs, positions and translations. |
| [`phil/doctrine-behaviours-bundle`](https://github.com/parler-haut-interagir-librement/doctrine-behaviours-bundle) | Doctrine behaviours in Symfony: listeners switched on by configuration, blame and owner from the signed-in user, trees, positions, translations, versions and their guarded restore. |
| [`phil/search-bundle`](https://github.com/parler-haut-interagir-librement/search-bundle) | Search with facets on the database, Meilisearch or Loupe, synonyms per language, and filters that work without JavaScript. |
| [`phil/search-loupe`](https://github.com/parler-haut-interagir-librement/search-loupe) | The Loupe adapter of `phil/search-bundle`: search indexes in SQLite files, with no server to run. |
| [`phil/search-synonyms`](https://github.com/parler-haut-interagir-librement/search-synonyms) | Synonym rules per language, checked for overlaps, read and written in the Solr and JSON formats. |
| [`phil/translation-memory`](https://github.com/parler-haut-interagir-librement/translation-memory) | Translation by segments: a translation memory, a glossary, quality checks, and XLIFF, TMX and TBX exchange. |
| [`phil/translation-bundle`](https://github.com/parler-haut-interagir-librement/translation-bundle) | Translation by segments in Symfony: a translation screen, a memory and glossaries in Doctrine, reviews and XLIFF exchange. |
| [`phil/media-bundle`](https://github.com/parler-haut-interagir-librement/media-bundle) | A media library for Symfony: images around their focal point in WebP or AVIF, purified SVG, responsive components and a file explorer. |
| [`phil/audio`](https://github.com/parler-haut-interagir-librement/audio) | Audio behind a port: transcoding with ffmpeg, metadata, waveform peaks computed on the server, excerpts, the creator's listening policy, and chapters and transcripts in timed text. |
| [`phil/audio-bundle`](https://github.com/parler-haut-interagir-librement/audio-bundle) | Audio in Symfony: renditions and waveforms made by Messenger jobs, uploads through the media library, and an accessible player component. |
| [`phil/video-bundle`](https://github.com/parler-haut-interagir-librement/video-bundle) | Videos in Symfony: hosted files or HLS in the browser's video element, YouTube and Vimeo behind a facade without tracking, captions, chapters and transcripts, progress counted only with consent. |
| [`phil/transcription`](https://github.com/parler-haut-interagir-librement/transcription) | Transcription of recordings behind one port: a whisper.cpp server run by the site, cloud adapters refused without the consent of the people heard, and a review before publication. |
| [`phil/editorial-calendar`](https://github.com/parler-haut-interagir-librement/editorial-calendar) | Plans publications ahead: items by content, language and author through their workflow, all-or-nothing batches, calendar views, conflicts and gaps, iCalendar. |

### Privacy, security and accessibility

| Package | Description |
|---|---|
| [`phil/accessibility-check`](https://github.com/parler-haut-interagir-librement/accessibility-check) | Check HTML or a UJO document for accessibility before it is published: contrast, headings, languages, images. |
| [`phil/accessibility-statement`](https://github.com/parler-haut-interagir-librement/accessibility-statement) | An accessibility statement written from the report of a check, in English and in French. |
| [`phil/creation-disclosure`](https://github.com/parler-haut-interagir-librement/creation-disclosure) | Say how a work was made, generative AI included, in the Digital Source Type vocabulary of the IPTC. |
| [`phil/indexing-consent`](https://github.com/parler-haut-interagir-librement/indexing-consent) | Whether a member's page may be indexed: listed, unlisted or private, read by the sitemap and the structured data. |
| [`phil/ip-anonymizer`](https://github.com/parler-haut-interagir-librement/ip-anonymizer) | IP addresses truncated, hashed under a key, or forgotten with their salt, before anything keeps them. |
| [`phil/analytics-bundle`](https://github.com/parler-haut-interagir-librement/analytics-bundle) | Analytics without a visitor identifier: counters per day, small numbers suppressed, reports and alerts. |
| [`phil/broken-flow-alerts`](https://github.com/parler-haut-interagir-librement/broken-flow-alerts) | An alert when a sign-up or a purchase stops completing, measured against the usual rhythm of the week. |
| [`phil/identity-client-bundle`](https://github.com/parler-haut-interagir-librement/identity-client-bundle) | A Symfony client of an OpenID Connect provider: back-channel logout, step-up authentication and serialized refreshes. |
| [`phil/identity-provider-bundle`](https://github.com/parler-haut-interagir-librement/identity-provider-bundle) | An open identity provider, one module per method: verified accounts, breach-checked passwords, passkeys, TOTP, sign-in links, step-up, and OpenID Connect with PKCE and rotating keys. |
| [`phil/email-address`](https://github.com/parler-haut-interagir-librement/email-address) | Email addresses a mail server can deliver to, with suggestions for slips such as gmial.com. |
| [`phil/rate-limit`](https://github.com/parler-haut-interagir-librement/rate-limit) | A build-time guard that refuses, in production, a rate limit counted separately on each server. |
| [`phil/rate-limit-bundle`](https://github.com/parler-haut-interagir-librement/rate-limit-bundle) | Rate limits declared on controllers, routes and forms, answered with 429 and the RateLimit headers. |
| [`phil/usage-meter`](https://github.com/parler-haut-interagir-librement/usage-meter) | Quotas that refuse an action before it exceeds its limit: counters per subject over calendar months, billing periods or rolling windows. |
| [`phil/usage-meter-bundle`](https://github.com/parler-haut-interagir-librement/usage-meter-bundle) | Quotas in Symfony: meters declared in configuration, counters in the database, limits from providers or subscriptions, rate limiters on a meter. |
| [`phil/safe-http`](https://github.com/parler-haut-interagir-librement/safe-http) | Read addresses given by members or other sites without reaching your own network: redirects checked one by one. |
| [`phil/idempotency`](https://github.com/parler-haut-interagir-librement/idempotency) | Run an operation once per idempotency key, replay its answer, and handle each Messenger message once. |
| [`phil/browser-security-bundle`](https://github.com/parler-haut-interagir-librement/browser-security-bundle) | Content Security Policy with nonces, HSTS, signed cookies and the other protections a browser enforces, page by page. |
| [`phil/legal-notices`](https://github.com/parler-haut-interagir-librement/legal-notices) | The legal notice and the privacy policy as versioned documents, filled from the identity of the publisher. |
| [`phil/legal-notices-bundle`](https://github.com/parler-haut-interagir-librement/legal-notices-bundle) | The legal notice and the privacy policy of a site at their own addresses, in each language, every version kept and linked from the footer. |
| [`phil/terms-bundle`](https://github.com/parler-haut-interagir-librement/terms-bundle) | Versioned terms of use: versions published once, an append-only record of acceptances, a gate for a new material version, reminders and public pages with their changes. |
| [`phil/accessibility-statement-bundle`](https://github.com/parler-haut-interagir-librement/accessibility-statement-bundle) | The accessibility statement of an application: versioned, published at /accessibility in English and French with the wording of WCAG, EN 301 549 or the Accessible Canada Act. |
| [`phil/resource-permissions`](https://github.com/parler-haut-interagir-librement/resource-permissions) | Permissions per resource on one subject-relation-object model: declared relations, rights inherited through a tree, bounded checks and listings. |
| [`phil/access-bundle`](https://github.com/parler-haut-interagir-librement/access-bundle) | Per-item visibility, sharing levels that expire on their own, access groups and a logged "view as this member", decided by Symfony voters. |
| [`phil/organizations-bundle`](https://github.com/parler-haut-interagir-librement/organizations-bundle) | Organizations in Symfony: members identified by an opaque subject, roles and teams, units in a tree whose roles hold below them, invitations limited in time and uses, joining by a verified email domain, and acting on behalf of an organization with the real author recorded. |
| [`phil/access-codes`](https://github.com/parler-haut-interagir-librement/access-codes) | Single-use codes in named batches, given once and stored as keyed hashes, redeemed atomically with attempts bounded by a rate limiter. |
| [`phil/access-links`](https://github.com/parler-haut-interagir-librement/access-links) | Invitations by personal link: a token stored as a keyed hash, an answer editable until closure, and reminders to non-respondents only. |
| [`phil/privacy-bundle`](https://github.com/parler-haut-interagir-librement/privacy-bundle) | Privacy law in code: personal fields declared, logs redacted, export and erasure, retention purges, cookie consent refused by default, requests by verified link and anonymized database copies. |
| [`phil/audit-bundle`](https://github.com/parler-haut-interagir-librement/audit-bundle) | An append-only audit trail: hash-chained rows indexed by actor, action and record, salted commitments on personal fields, and external anchors. |
| [`phil/audit-viewer`](https://github.com/parler-haut-interagir-librement/audit-viewer) | The audit trail read in EasyAdmin: filters, the history of a record, the state of each hash chain and an audited CSV export. |
| [`phil/user-secrets-bundle`](https://github.com/parler-haut-interagir-librement/user-secrets-bundle) | The API keys and OAuth tokens people entrust to a site: sealed by envelope encryption with rotating keys, refreshed under a lock, revocable and erased with the account. |
| [`phil/api-keys-bundle`](https://github.com/parler-haut-interagir-librement/api-keys-bundle) | Access tokens for a public API: hashed and shown once, scoped, expiring, revocable and rotated, checked by Symfony Security. |
| [`phil/guardian-consent`](https://github.com/parler-haut-interagir-librement/guardian-consent) | Parental consent for members under the age of consent: age brackets per jurisdiction, consent per feature by signed email link, dated proofs and withdrawal. |

### PHIL UX

| Package | Description |
|---|---|
| [`phil/ux-css`](https://github.com/parler-haut-interagir-librement/ux-css) | phil-ux, a mostly classless CSS framework on `--phil-*` tokens, light and dark, whose themes are checked for contrast. |
| [`phil/ux-kit`](https://github.com/parler-haut-interagir-librement/ux-kit) | The PHIL UX kit for the Symfony UX Toolkit: the recipes of the Shadcn kit, styled with phil-ux, copied by `ux:install`. |
| [`phil/ux-bundle`](https://github.com/parler-haut-interagir-librement/ux-bundle) | The PHIL UX form theme on every form of a Symfony application, and the stylesheet of `phil/ux-css` in AssetMapper. |
| [`phil/ux-easyadmin-bundle`](https://github.com/parler-haut-interagir-librement/ux-easyadmin-bundle) | The EasyAdmin back-office in the colours, corners and fonts of the phil-ux tokens, light and dark. |
| [`phil/ux-kit-shadcn`](https://github.com/parler-haut-interagir-librement/ux-kit-shadcn) | Tailwind versions, in the style of the Shadcn kit, of the extra PHIL UX recipes, under the same names and props, copied by `ux:install`. |

### Integrations

| Package | Description |
|---|---|
| [`phil/standard-webhooks`](https://github.com/parler-haut-interagir-librement/standard-webhooks) | Sign and verify webhooks that any Standard Webhooks library can send or check. |
| [`phil/standard-webhooks-bundle`](https://github.com/parler-haut-interagir-librement/standard-webhooks-bundle) | Verified webhook deliveries as controller arguments, and signed webhooks sent from Symfony. |
| [`phil/circuit-breaker`](https://github.com/parler-haut-interagir-librement/circuit-breaker) | One circuit breaker per third party: calls stop at once when it keeps failing, and resume when it recovers. |
| [`phil/circuit-breaker-bundle`](https://github.com/parler-haut-interagir-librement/circuit-breaker-bundle) | Circuits declared in configuration, their states shared by the workers, and HTTP clients behind their circuits. |
| [`phil/bounce-parser`](https://github.com/parler-haut-interagir-librement/bounce-parser) | Know what every message on your bounce mailbox says, and what to do about the address it names. |
| [`phil/api-contract`](https://github.com/parler-haut-interagir-librement/api-contract) | Both sides of an HTTP API contract: problem details, deprecation signals, and an OpenAPI document checked against levels of rules. |
| [`phil/api-contract-bundle`](https://github.com/parler-haut-interagir-librement/api-contract-bundle) | Problem details, idempotent and deprecated controllers, and the OpenAPI document checked in CI, for Symfony. |
| [`phil/ai-bundle`](https://github.com/parler-haut-interagir-librement/ai-bundle) | Language-model tasks on Symfony AI, with quotas, budgets and people's own keys, and a cost journal that never stores a prompt or an answer. |
| [`phil/automations`](https://github.com/parler-haut-interagir-librement/automations) | A no-code automation builder for site events: JSON definitions, delays, filters and branches, retries and compensation, run by Messenger. |
| [`phil/lti`](https://github.com/parler-haut-interagir-librement/lti) | LTI 1.3 on both sides, tool and platform: the OpenID Connect launch, Deep Linking 2.0, typed roles and claims, and the grade and roster services of LTI Advantage. |
| [`phil/lti-bundle`](https://github.com/parler-haut-interagir-librement/lti-bundle) | LTI 1.3 in Symfony: the routes of the launch, Deep Linking and the key set, an authenticator for verified launches, stores on Doctrine DBAL and scores sent through Messenger. |
| [`phil/elearning-packages`](https://github.com/parler-haut-interagir-librement/elearning-packages) | SCORM 1.2, SCORM 2004 and cmi5 packages read safely, without a learning platform: the archive checked before any file is read, the manifest read into values. |
| [`phil/courses-bundle`](https://github.com/parler-haut-interagir-librement/courses-bundle) | Online courses in Symfony: modules and activities, enrollments, progress and completion, release by date, SCORM and cmi5 packages imported, cmi5 launches checked, a local or remote xAPI store. |
| [`phil/mime-reader`](https://github.com/parler-haut-interagir-librement/mime-reader) | Raw emails read as hostile input: header fields, nested MIME parts and report fields within limits, bodies decoded on demand into UTF-8. |

### Operations

| Package | Description |
|---|---|
| [`phil/feature-flags-bundle`](https://github.com/parler-haut-interagir-librement/feature-flags-bundle) | Turn a feature off without a deploy, run an A/B test, and force a failing third party's circuit open. |
| [`phil/console-bundle`](https://github.com/parler-haut-interagir-librement/console-bundle) | Safer Symfony console commands. |
| [`phil/migration`](https://github.com/parler-haut-interagir-librement/migration) | Release notes and news that Doctrine migrations send to chat once a run is over. |
| [`phil/migration-bundle`](https://github.com/parler-haut-interagir-librement/migration-bundle) | Each run of `doctrine:migrations:migrate` announced once on your chat channels. |
| [`phil/backup`](https://github.com/parler-haut-interagir-librement/backup) | Back a database and its media files up, encrypted and rotated, restore them behind a guard, and prove that they restore. |
| [`phil/backup-bundle`](https://github.com/parler-haut-interagir-librement/backup-bundle) | Back a Symfony application's database and media files up from its configuration, restore them behind a guard, and test that they restore. |
| [`phil/monitoring-bundle`](https://github.com/parler-haut-interagir-librement/monitoring-bundle) | The surveillance of a Symfony site: its health, a maintenance mode, the heartbeats of its scheduled tasks and its Messenger queues. |
| [`phil/reporting-bundle`](https://github.com/parler-haut-interagir-librement/reporting-bundle) | Dashboards and reports for Symfony applications: figures contributed by any bundle, charts drawn without a script, reports exported, scheduled and delivered. |
| [`phil/route-access-check`](https://github.com/parler-haut-interagir-librement/route-access-check) | Every route has an access rule, or says why it is public: checked in continuous integration, before it ships. |
| [`phil/route-access-check-bundle`](https://github.com/parler-haut-interagir-librement/route-access-check-bundle) | `bin/console phil:route-access:check`: every route of the application has an access rule, or the CI step fails. |
| [`phil/data-table-export`](https://github.com/parler-haut-interagir-librement/data-table-export) | Tables exported as CSV guarded against formula injection, or as XLSX written in blocks, the same bytes for the same sheet. |
| [`phil/incident-mode`](https://github.com/parler-haut-interagir-librement/incident-mode) | An incident mode: presets that suspend sign-ups, invitations or messages for a required duration, lifted automatically. |
| [`phil/incident-mode-bundle`](https://github.com/parler-haut-interagir-librement/incident-mode-bundle) | The incident mode in Symfony: sign-ups, invitations or forms paused during an attack, an honest public notice, lifting on a schedule, a log and an administration page. |
| [`phil/status-page-bundle`](https://github.com/parler-haut-interagir-librement/status-page-bundle) | A service status page served as HTML, JSON and Atom without any script, and a static snapshot pushed off the monitored infrastructure. |
| [`phil/error-tracking`](https://github.com/parler-haut-interagir-librement/error-tracking) | Error tracking kept at home: events grouped by a stable fingerprint, scrubbed of personal data and secrets before anything is written. |
| [`phil/error-tracking-bundle`](https://github.com/parler-haut-interagir-librement/error-tracking-bundle) | phil/error-tracking in Symfony: requests, workers, commands and browsers captured, groups kept with a purge, pages by role and chat alerts for new groups and regressions. |
| [`phil/data-table-bundle`](https://github.com/parler-haut-interagir-librement/data-table-bundle) | Data tables as a Live Component: sorting, filters, pagination, row and bulk actions, inline editing, and the same state in the address without JavaScript. |

Every package is at `0.x`: its API may still change between minor versions, as its CHANGELOG records. More packages
join the showcase by small daily groups.

## Installing a package

Require the package you need; a bundle brings its library along:

```sh
composer require phil/money-bundle
```

A profile installs a family of packages in one require: `phil/profile-secure-input` for what users send,
`phil/profile-editorial` for editorial content.

Each package's README and `doc/` say what it requires, how to configure it and what it leaves to the application.

## Layout

- `packages/<name>/`: one package per folder, installable on its own, with its `composer.json`, README in English and
  in French, CHANGELOG, licence, documentation in `doc/` (French mirror in `doc/fr/`) and tests.
- The root ties them together through a Composer path repository and holds the shared quality tooling, which comes
  from [`phil/dev-tools`](https://github.com/parler-haut-interagir-librement/dev-tools).

## Contributing

Issues and pull requests go to **this repository**, never to the split repositories, which are overwritten at each
split. Read the [contribution guide](CONTRIBUTING.md) and the [code of conduct](CODE_OF_CONDUCT.md). Report a
vulnerability privately, as the [security policy](SECURITY.md) explains, never in a public issue.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE). Each package carries its own licence file.
