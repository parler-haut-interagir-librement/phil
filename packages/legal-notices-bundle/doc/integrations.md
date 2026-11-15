# Integrations

## phil/markup

With phil/markup installed, the bodies are rendered by its `Markup` — Markdown, UJO (Universal JSON Output), HTML,
plain text and every format it reads — and purified at its `all` level. `markup` names a `Markup` service of the
application instead, with its own add-ons. Without phil/markup, only the `text` format is rendered; another format
makes `phil:legal-notices:check` report the body.

## phil/privacy-bundle

```yaml
phil_legal_notices:
    retention:
        source: App\Privacy\RetentionSchedule     # the RetentionScheduleSource the privacy bundle reads
        labels:
            R-001: { en: Your account, fr: Votre compte }
```

Each rule of the schedule that holds personal information — every rule with `personal_only: false` — becomes a line
of the privacy policy: what it keeps, named by its label in the locale, else by its store, and its period in words. A
fixed period is "2 years", "2 ans"; a period until an event shows the event the schedule names; the note of a period
not set yet stays internal. The body names one period with `${retention.R-001}`. Another source of periods is a
service of the application aliased to `Phil\LegalNotices\Contract\RetentionDurations`.

## Consent categories

The categories under `consent.categories` are listed under the privacy policy, necessary or set only with consent,
with their cookies; `${consent.<key>}` names one in the body. A consent banner that keeps its own categories gives
them through a `ConsentCategories` service named by `consent.source`.

## phil/terms-bundle

`footer.terms_policies` adds the policies of phil/terms-bundle to the footer links, through its route
`phil_terms_policy` and the labels of its `phil_terms` domain. The terms pages show the same links when the layout
they extend — its `pages.layout` option — includes `@PhilLegalNotices/_links.html.twig`.

## phil/sitemap-bundle

With phil/sitemap installed, `LegalUrlProvider` adds the page of each document in force to the section
`sitemap_section`, last changed when its version took effect. Localized routes give the page of the locale the
sitemap is built in.

## phil/structured-data-bundle

With phil/structured-data-bundle installed, `LegalPagesStructuredData` describes the page of a document in force and the
page of each of its versions as a `WebPage`, at the page's `#webpage` so that it merges with the node of the site: the
translated name of the document, the language, the dates it was published and took effect, its version number, and the
publisher named by the identity, with its contact address and its postal address. The history and every other page give
no node.

## phil/accessibility-statement-bundle

With phil/accessibility-statement-bundle installed, the link to the statement comes after the other footer links,
through `FooterLinkProvider`.

## PHIL UX

The pages draw their badges and their table with the recipes `badge` and `table` of the PHIL UX kit, which `ux:install`
copies into the application ([Pages](pages.md#the-recipes-of-the-kit)); the rest is semantic HTML that the stylesheet
of phil/ux-css dresses without a class. An application that changes a page copies its template under
`templates/bundles/PhilLegalNoticesBundle/` and calls the same recipes, or others of the kit, by the names
`phil_legal_notices_kit()` gives.

## EasyAdmin

The bundle has no administration page: the versions are published by writing them in the configuration, or by the
application's own `DocumentSource`. An application that publishes them from an EasyAdmin dashboard writes that page in
its dashboard, where phil/ux-easyadmin-bundle gives it the colours of PHIL UX like every other page of the back-office.
