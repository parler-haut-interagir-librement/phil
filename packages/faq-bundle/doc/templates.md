# Templates

## The component

```twig
<twig:PhilFaq />
```

`PhilFaq` draws the questions of the request's language, filtered by the request's `q`. Its props:

| Prop | Default | What it sets |
|---|---|---|
| `page` | `null` | A `FaqPage` already read, as the bundle's page passes it; read from the request when null. |
| `query` | `null` | The search; the request's `q` when null. |
| `locale` | `null` | The language; the request's when null. |
| `action` | `null` | Where the search form goes; the current path when null. |
| `headingLevel` | `2` | The level of the section headings, 2 to 5. |
| `structuredData` | `true` | Whether the JSON-LD is written. |
| `nonce` | `null` | The nonce of the page's content security policy, for the JSON-LD script. |

The bundle's page, `@PhilFaq/page.html.twig`, extends `phil_faq.layout`, writes the `h1` and the component. An
application overrides any template under `templates/bundles/PhilFaqBundle/`.

## What it draws

- A search form, `method="get"`, with its label, a field `q` and its button: without JavaScript, the server filters.
- A live region that says how many questions match, and a link that shows them all again.
- After a search that found nothing, the sentence that says so and the link to `contact_url`.
- Each section, a `section` whose `id` is `faq-` and its key, under its heading.
- Each question, a `details` element whose `id` is its anchor, its wording in the `summary`, its answer, a link to its
  anchor, a button that copies the full link, the day it changed, and the vote form. A question shown in its fallback
  language carries that `lang`.
- The `FAQPage` JSON-LD of the questions shown.

The questions are open after a search, and the one a plain form voted for is open when the page comes back. With the
controller, the question named by the address opens when the page loads and when the address changes. No question is
ever an accordion without an anchor: each one has its own address.

The recipes are those of the kit, under their Toolkit names; the structure carries the classes of the kit's stylesheet —
`isAccordion`, `isAccordionItem`, `isAccordionTrigger`, `isAccordionContent` — and BEM classes of its own, `phil-faq__*`,
for a site's styles.

## The search

The search and the questions are folded alike, on the server and in the browser: compatibility forms decomposed,
accents and case dropped, every run of what is neither a letter nor a digit made one space; a search keeps its first
100 characters and 10 words. A question matches when its wording or its answer holds every word.

A search that finds nothing is counted once it has three characters: by the page when the form reaches the server, and
by the controller after a pause of 1.5 seconds without typing, or at once on Enter, once per search and page.

## The vote

The form posts `helpful` — `yes` or `no` —, the page's vote token, the CSRF token `phil_faq` and the path to come back
to. Without JavaScript, the server answers `303` to that path, with `voted` and the anchor, and the page thanks the
reader; a path that is not one of the site is replaced by the page of questions. With the controller, the vote is sent
in JSON and the form is replaced by the thanks; a question already voted for from this browser shows the thanks when
the page loads.

| Answer | Status | JSON |
|---|---|---|
| Counted | `200`, or `303` for a plain form | `{"yes": 3, "no": 1}` |
| Neither yes nor no | `422` | `{"error": "invalid"}` |
| CSRF check or token refused, token expired | `403` | `{"error": "refused"}` |
| No question with this anchor | `404` | `{"error": "unknown"}` |
| This token already voted for this question | `409` | `{"error": "already"}` |
| Too many votes on this question | `429`, with `Retry-After` | `{"error": "too_many"}` |

A plain form that is refused shows `@PhilFaq/stopped.html.twig`, with the reason and the way back.

The page's vote token is its own, so the bundle's page is sent `Cache-Control: private`. A site that caches the page in
a shared cache gives every reader the same token: each question then counts one vote per token's lifetime.

## The JSON-LD

`FaqStructuredData::graph()` gives the `FAQPage` of a page — its address, its title when given, its language, and each
question with its accepted answer as purified HTML — and the component writes it with `JsonLd::script()`, which escapes
what could close the element. The JSON-LD holds no token. A page without question writes nothing.

## The Markdown of an `llms.txt`

`FaqMarkdown::export()` writes the questions of a page as Markdown: a heading, one heading per section, one per question
linked to its anchor, and the text of each answer. An application serves it in its `llms.txt`, or as a page that file
links to:

```php
$page = $pages->page(Locale::fromString('en'));
$markdown = $markdownExport->export($page, 'Frequently asked questions', 'https://example.org/faq/');
```

## Accessibility

- The search has a visible label and a submit button; its result is said in a polite live region.
- Each question is a native `details` element: the keyboard, screen readers and the browser's find in page open it.
- The vote is a `fieldset` with its `legend`; the thanks is a `status`, a failure an `alert`.
- The copy button stays hidden while no script can copy, and says what happened in a live region of its own.
- The section headings follow the page's outline through `headingLevel`.
