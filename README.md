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
| [`phil/epub-bundle`](https://github.com/parler-haut-interagir-librement/epub-bundle) | Books built by sources registered by attribute, written now or by a Messenger message, with their fonts and images. |
| [`phil/latex-mathml`](https://github.com/parler-haut-interagir-librement/latex-mathml) | LaTeX formulas converted to MathML Core, any other command refused with its position, length, depth and expansion bounded. |
| [`phil/svg-blocks`](https://github.com/parler-haut-interagir-librement/svg-blocks) | The math, mermaid, chart and QR code blocks of UJO documents drawn once when saved, as signed SVG or MathML. |

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
| [`phil/geo-bundle`](https://github.com/parler-haut-interagir-librement/geo-bundle) | Stored coordinates, distances in Twig, configured geocoders and nearest-first lists for Symfony applications. |
| [`phil/contact`](https://github.com/parler-haut-interagir-librement/contact) | Contact cards: names ordered by their culture, emails, phone lines and handles, written as vCard, JSContact and JSON-LD. |
| [`phil/contact-bundle`](https://github.com/parler-haut-interagir-librement/contact-bundle) | A contact card form, a JSON column, vCard and JSContact downloads, and person names in Twig, for Symfony. |

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
| [`phil/checkin`](https://github.com/parler-haut-interagir-librement/checkin) | The check-in of an event or a venue: a code read at a door, decided at once, recorded once, online or offline. |
| [`phil/listings`](https://github.com/parler-haut-interagir-librement/listings) | The core of listings: a classified ad, a job offer, a search for partners — written, moderated, answered and expired by the same rules. |
| [`phil/open-badges`](https://github.com/parler-haut-interagir-librement/open-badges) | Open Badges 3.0 that anyone can verify: issued, signed, baked into the image, revoked, endorsed and gathered in a learner record. |
| [`phil/directory-bundle`](https://github.com/parler-haut-interagir-librement/directory-bundle) | A directory of listings with their history, editors and relations, each found as its author decides. |
| [`phil/checkin-bundle`](https://github.com/parler-haut-interagir-librement/checkin-bundle) | The check-in desk in Symfony: scan, manifest and synchronisation endpoints, and a desk that keeps checking in offline. |
| [`phil/comments-bundle`](https://github.com/parler-haut-interagir-librement/comments-bundle) | Comments on a whole page or on a passage of a text, with anchors that follow edits and suggested replacements. |
| [`phil/community-notes`](https://github.com/parler-haut-interagir-librement/community-notes) | Community notes written by qualified members and published by an explainable cross-agreement rule. |
| [`phil/poll-bundle`](https://github.com/parler-haut-interagir-librement/poll-bundle) | Polls and ballots in Symfony: anonymous ballots changed by receipt, and the single transferable vote by Meek's method with its rounds. |

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

### Content and search

| Package | Description |
|---|---|
| [`phil/content-bundle`](https://github.com/parler-haut-interagir-librement/content-bundle) | Content by language, stage and version: drafts published through a workflow, bodies published as UJO, routes that keep old slugs, and an administration. |
| [`phil/locale-dimensions`](https://github.com/parler-haut-interagir-librement/locale-dimensions) | Languages, versions and the fallback chains between languages, as plain value objects. |
| [`phil/publishing`](https://github.com/parler-haut-interagir-librement/publishing) | The events a page sends when it goes online or offline, for the listeners that follow it. |
| [`phil/scheduled-transitions-bundle`](https://github.com/parler-haut-interagir-librement/scheduled-transitions-bundle) | Publish or unpublish at a date: workflow transitions planned and run by the Symfony Scheduler. |
| [`phil/doctrine-behaviours`](https://github.com/parler-haut-interagir-librement/doctrine-behaviours) | Doctrine behaviours: timestamps, authors, soft delete, trees, slugs, positions and translations. |
| [`phil/search-bundle`](https://github.com/parler-haut-interagir-librement/search-bundle) | Search with facets on the database, Meilisearch or Loupe, synonyms per language, and filters that work without JavaScript. |
| [`phil/search-loupe`](https://github.com/parler-haut-interagir-librement/search-loupe) | The Loupe adapter of `phil/search-bundle`: search indexes in SQLite files, with no server to run. |
| [`phil/search-synonyms`](https://github.com/parler-haut-interagir-librement/search-synonyms) | Synonym rules per language, checked for overlaps, read and written in the Solr and JSON formats. |
| [`phil/translation-memory`](https://github.com/parler-haut-interagir-librement/translation-memory) | Translation by segments: a translation memory, a glossary, quality checks, and XLIFF, TMX and TBX exchange. |
| [`phil/translation-bundle`](https://github.com/parler-haut-interagir-librement/translation-bundle) | Translation by segments in Symfony: a translation screen, a memory and glossaries in Doctrine, reviews and XLIFF exchange. |
| [`phil/media-bundle`](https://github.com/parler-haut-interagir-librement/media-bundle) | A media library for Symfony: images around their focal point in WebP or AVIF, purified SVG, responsive components and a file explorer. |

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
| [`phil/email-address`](https://github.com/parler-haut-interagir-librement/email-address) | Email addresses a mail server can deliver to, with suggestions for slips such as gmial.com. |
| [`phil/rate-limit`](https://github.com/parler-haut-interagir-librement/rate-limit) | A build-time guard that refuses, in production, a rate limit counted separately on each server. |
| [`phil/rate-limit-bundle`](https://github.com/parler-haut-interagir-librement/rate-limit-bundle) | Rate limits declared on controllers, routes and forms, answered with 429 and the RateLimit headers. |
| [`phil/safe-http`](https://github.com/parler-haut-interagir-librement/safe-http) | Read addresses given by members or other sites without reaching your own network: redirects checked one by one. |
| [`phil/idempotency`](https://github.com/parler-haut-interagir-librement/idempotency) | Run an operation once per idempotency key, replay its answer, and handle each Messenger message once. |
| [`phil/browser-security-bundle`](https://github.com/parler-haut-interagir-librement/browser-security-bundle) | Content Security Policy with nonces, HSTS, signed cookies and the other protections a browser enforces, page by page. |
| [`phil/legal-notices`](https://github.com/parler-haut-interagir-librement/legal-notices) | The legal notice and the privacy policy as versioned documents, filled from the identity of the publisher. |

### PHIL UX

| Package | Description |
|---|---|
| [`phil/ux-css`](https://github.com/parler-haut-interagir-librement/ux-css) | phil-ux, a mostly classless CSS framework on `--phil-*` tokens, light and dark, whose themes are checked for contrast. |
| [`phil/ux-kit`](https://github.com/parler-haut-interagir-librement/ux-kit) | The PHIL UX kit for the Symfony UX Toolkit: the recipes of the Shadcn kit, styled with phil-ux, copied by `ux:install`. |
| [`phil/ux-bundle`](https://github.com/parler-haut-interagir-librement/ux-bundle) | The PHIL UX form theme on every form of a Symfony application, and the stylesheet of `phil/ux-css` in AssetMapper. |
| [`phil/ux-easyadmin-bundle`](https://github.com/parler-haut-interagir-librement/ux-easyadmin-bundle) | The EasyAdmin back-office in the colours, corners and fonts of the phil-ux tokens, light and dark. |

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
| [`phil/status-page-bundle`](https://github.com/parler-haut-interagir-librement/status-page-bundle) | A service status page served as HTML, JSON and Atom without any script, and a static snapshot pushed off the monitored infrastructure. |
| [`phil/error-tracking`](https://github.com/parler-haut-interagir-librement/error-tracking) | Error tracking kept at home: events grouped by a stable fingerprint, scrubbed of personal data and secrets before anything is written. |

Every package is at `0.x`: its API may still change between minor versions, as its CHANGELOG records. More packages
join the showcase by small daily groups.

## Installing a package

Require the package you need; a bundle brings its library along:

```sh
composer require phil/money-bundle
```

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
