# The translation screen

## Routes

| Route | Method and path | Does |
|---|---|---|
| `phil_translation_page` | `GET /{document}/{language}` | The page of a document in a target language |
| `phil_translation_save` | `POST /{document}/{language}/segment` | One action on one segment, then back to it with a `303` |
| `phil_translation_comment` | `POST /{document}/{language}/comment` | `action=add` comments a segment, `action=remove` removes a comment, then back with a `303` |
| `phil_translation_machine` | `POST /{document}/{language}/machine` | Machine translations proposed to the untranslated segments, then back with their count |
| `phil_translation_export` | `GET /{document}/{language}/xliff` | The document as an XLIFF 2.1 attachment |
| `phil_translation_import` | `POST /{document}/{language}/xliff` | An XLIFF file merged, and the page with a report |

A document reference is 1 to 255 letters, digits, `_`, `.`, `:` or `-`; a language, 2 to 35 letters, digits or `-`.
The same file imports the [glossary pages](glossary-pages.md).

## The application's services

```php
use Phil\TranslationBundle\Application\SourceDocument;
use Phil\TranslationBundle\Application\SourceDocuments;
use Phil\TranslationMemory\LanguageTag;

final readonly class ArticleSources implements SourceDocuments
{
    public function __construct(private ArticleRepository $articles) {}

    public function find(string $document): ?SourceDocument
    {
        $article = str_starts_with($document, 'article:') ? $this->articles->find(substr($document, 8)) : null;

        return null === $article ? null : new SourceDocument($article->content(), LanguageTag::of($article->language()), $article->title());
    }
}
```

`TranslationPolicy` answers three questions for the current user: `canTranslate()` — see the page, write and propose
targets, confirm and clear them, dismiss checks, comment, ask for machine translations, export and import —,
`canReview()` — mark targets as reviewed and remove comments — and `canEditGlossary()` — edit the glossary of a pair
of languages.

| Answer | When |
|---|---|
| 404 | No `sources` or no `policy` configured; a document the application does not find; a language that is not a tag; a segment the document does not have; a comment it does not have; machine translation while it is off |
| 403 | The target language is the source's; the user may not translate; a review or the removal of a comment by a user who may not review; a form from another site |
| 400 | An action the screen does not know |
| 422 | A change the segment cannot take; a check that is not a flag's name, or flags too long; a comment empty or longer than 4000 characters; an import without a file, of a file larger than 10 MiB, unreadable, or of other languages — the page shows why |
| 502 | The machine translator failed, or did not answer one target per source — the page says so, without what the service answered |

## The page

The page previews the document's preparation, storing nothing, then shows its progress, the button of machine
translation when a translator is configured, the export link and the import form, and one row per segment: its state
and key, the mark `machine translation` on a target the machine proposed, its source as written — tags shown as text
—, what changed in a stale source, the glossary's terms and notes, the target a regional variant inherits, a form with
the target, the glossary's issues, the quality checks with a button that dismisses each, the segment's flags, the
memory's matches for any segment not yet usable, each with a button that proposes it, and the comments with a form to
add one.

| Button | Action | Shown |
|---|---|---|
| Save | `save`: translated | always |
| Propose | `suggest` | always |
| Review | `review` | when the user may review and the segment has a target that is not stale |
| Still right | `confirm` | on a stale segment |
| Clear | `clear` | when the segment has a target |
| Dismiss “check” | `dismiss`: the flag `ignore-<check>` | on each issue of a quality check |

Forms post plain HTML and work without JavaScript. A form from another site is refused by `SameOriginGuard`, which
asks for two things:

- the standard request headers name this site: `Sec-Fetch-Site: same-origin`, or else an `Origin` that is the
  screen's own;
- the form passes Symfony's stateless CSRF check of the token `phil_translation`. Each form carries the field
  `_csrf_token`, rendered with the name of the token's cookie (`csrf-token` by default, FrameworkBundle's
  `csrf_protection.cookie_name`). When the layout loads Stimulus, the screen's `csrf` controller
  (`phil--translation-bundle--csrf`, mapped for AssetMapper) replaces it, as a form is sent, by a new random token
  and sets it in a cookie of that name (`__Host-` first and `secure` over HTTPS, same-site only), so that the field
  and the cookie carry the same token; without JavaScript the field keeps the name and the origin alone decides.
  `SameOriginCsrfTokenManager` refuses a token that is neither, and a cookie of another token when the browser's
  headers do not name this site; FrameworkBundle clears the cookie on the answer.

The bundle adds the token to FrameworkBundle's `csrf_protection.stateless_token_ids`, which turns
`framework.csrf_protection` on: leave it on.
With `csrf_protection: false`, the container stops with a `LogicException` that says so.

## The layout

The screen extends the template named by `layout`, which defines the blocks `title` and `content`. The bundle's own,
`@PhilTranslation/layout.html.twig`, is a bare page with a few styles and no script; point `layout` at the
application's base template, with its Stimulus application, to show the screen in its back office with the `csrf`
controller. The page's markup sits in `<main class="phil-translation">`, with the classes `progress`, `variant`, `done`,
`error`, `report`, `state state-<state>`, `machine`, `change`, `inherited`, `terms`, `issues`, `checks`, `flags`,
`suggestions`, `comments` and `comment`.
