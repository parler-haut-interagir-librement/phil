# Pages

## The routes

| Route | Path | What it shows |
|---|---|---|
| `phil_newsletter_archive` | `/` | The published issues of the site, newest sent first, a page at a time. |
| `phil_newsletter_archive_issue` | `/{slug}` | A published issue. |
| `phil_newsletter_archive_link` | `/shared/{token}` | An issue shared by a link, published or not. |

The list reads three query parameters: `list`, the key of a list; `tag`, a tag; `page`, from 1 to 1000. A parameter
out of shape, a page past the last one, a slug of no published issue, a token revoked, malformed or never issued, and
a token of another site's issue all answer 404.

## The templates

| Template | Variables |
|---|---|
| `@PhilNewsletterArchive/archive/layout.html.twig` | The page the others extend, with the blocks `title`, `meta` and `body`. |
| `@PhilNewsletterArchive/archive/index.html.twig` | `page` (`IssuePage`), `lists` (key ⇒ name), `tags`, `list`, `tag`. |
| `@PhilNewsletterArchive/archive/issue.html.twig` | `issue` (`ArchivedIssue`), `shared` (whether it is read by its link). |

The templates write plain HTML — headings, lists, `nav`, `article`, `time` — that the phil-ux stylesheet dresses
without a class; the few classes they carry, `phil-newsletter-archive__…`, are hooks for an application's own style.
An application overrides them in `templates/bundles/PhilNewsletterArchiveBundle/`, the layout first, to wear its own.
The texts are in the translation domain `phil_newsletter_archive`, in English and French.

The web version is written unescaped, `issue.html|raw`: it was purified by `phil/markup` when the issue was
published.

## Search engines and sharing

The page of a published issue gives its canonical address, `<link rel="canonical">`, and its Open Graph type, title,
address and description — the preheader. The list gives the canonical address of its page and filters.

A sharing image is the application's: the block `meta` of the layout is where it goes, from `phil/social-cards` for
one.

## A shared page

The page of a link answers `Cache-Control: no-store, private`, `Referrer-Policy: no-referrer` and
`X-Robots-Tag: noindex, nofollow`, and says the same in its `meta` elements: no cache keeps it, no search engine indexes
it, and no `Referer` carries its token to the sites it links to. It gives a canonical address only when the issue is
published too, and links its tags only then.
