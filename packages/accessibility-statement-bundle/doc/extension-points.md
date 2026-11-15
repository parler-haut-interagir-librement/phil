# Extension points

What an application implements, replaces or relies on.

## The latest audit

`audit.source` names a service implementing `Phil\AccessibilityStatement\Contract\AuditSource` of
`phil/accessibility-statement`, instead of the directory of reports: a table where a crawler stores its reports, a
service that renders the pages of the site and checks them with `AccessibilityChecker`. `latest()` returns an
`AuditResult`, or `null` when there is none.

## The versions

`Domain\Contract\StatementRepository` is the store of the versions, aliased to `ConfiguredStatements` or
`DbalStatements`. An application that keeps them elsewhere decorates or replaces the alias with its own: `latest()`,
`find()`, `publications($limit)` — the moment of each version, newest first —, `writable()`, and `add()`, which never
changes a version already published and throws `StatementNotStored` when it cannot keep one.

## Regenerating

`Application\StatementRegenerator` (public) gives the next version — `preview()` — or publishes it — `publish()` —, for
an application that regenerates the statement from a scheduled task, a message handler or its back office rather than
from the console. It throws `NothingToRegenerate` without a statement or an audit.

## Publishing

`Application\StatementPublisher` (public, with `admin`) gives the draft of the next version — `draft()`, JSON — and
publishes a document — `publish()` —, refusing with `PublicationRefused` a document that does not read, is not the next
number, or that the store does not keep: for a back office of the application's own instead of the page of the bundle
([Administration](administration.md)).

## The page

The layout, the page, the table of versions, the administration page and the wordings are templates the application
replaces ([The page](page.md)); the translation domain `phil_accessibility_statement` takes other languages and other
sentences. The parts are recipes of the kit, found under `kit_subdirectory` or the subdirectory of the Toolkit. The
feedback channel of a form is the form `feedback_form` names, or the route `feedback_route` names. With
`phil/legal-notices-bundle`, the link joins the legal links of a footer by the tag `phil_legal_notices.footer_link` of
`StatementFooterLink`, which an application removes or replaces like any service.
