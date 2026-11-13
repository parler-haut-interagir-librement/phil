# The page

Under a published article, a page of a site, a product sheet, readers comment on the whole page. The comments
controller does it beside the block editor; where a page has no script at all, the Twig component `PhilComments:Page`
draws the same threads on the server, and every action is a plain form that posts and comes back to the page.

```twig
<twig:PhilComments:Page subject="article:42" />
```

`subject` is a `ResourceReference` or its text. The component needs Symfony UX Twig Component, the route of
[`config/page_routes.php`](installation.md#the-routes) and the recipes of the kit ([installation](installation.md#the-recipes-of-the-kit)).

## What it draws

A `<section id="phil-comments-page">` headed *On the page*:

- for nobody signed in — the application's `AuthorProvider` names no one —, an `Alert` that asks to sign in, and no
  form;
- for a reader the policy does not let read (`mayRead`), a sentence that says so;
- otherwise, the threads on the whole page, oldest first, each a `Card`: a `Badge` *Resolved by …* in its header when
  it is resolved, its comments — author, date, *edited* when changed —, and in its footer the reply form while it is
  open and the button *Resolve* or *Reopen* when the policy allows it (`mayResolve`);
- under each comment its author may still edit, or a moderator may edit (`mayModerate`), a `<details>` *Edit* with its
  form, and *Delete*;
- last, when the reader may comment (`mayComment`), the form *Comment on the page*.

Threads on passages are not listed: they belong to the comments controller, beside the text. Texts are escaped by Twig;
nothing the readers write is read as HTML.

| Recipe | Where |
|---|---|
| `Alert`, `Alert:Description` | The request to sign in |
| `Card`, `Card:Header`, `Card:Content`, `Card:Footer` | Each thread |
| `Badge` | A resolved thread |
| `Field`, `Field:Label`, `Textarea` | Each form's text and its label |
| `Button` | Each action |

The recipes are found under the components subdirectory of the `ux_toolkit` configuration; in an environment where the
Toolkit is not enabled, as production often is, repeat it in the option `kit_subdirectory`. They have the names, props
and blocks of the Shadcn kit, so the section is drawn the same with PHIL UX or with Tailwind.

## The plain forms

Each form posts to the route `phil_comments_page`, at an address signed by Symfony's `UriSigner` that carries the page
commented on and the page to come back to: a browser can change neither. A form sends `_token`, `do` — `open`,
`reply`, `edit`, `delete`, `resolve` or `reopen` —, `id`, the thread or comment it acts on, and `body`, the text.

| Check | Answer when it fails |
|---|---|
| The author provider and the policy configured | `404` |
| The address signed | `403`, and the way back to the home page |
| Symfony's stateless CSRF check of the token `phil_comments`: the browser's `Sec-Fetch-Site`, `Origin` or `Referer` naming this site | `403` |
| Someone signed in | `401` |
| The text kept, the action known | `400` |
| The policy | `403` |
| The thread or the comment still there | `404` |

Done, the browser comes back to the page with `303 See Other`, at `#phil-comments-page`. Stopped, it gets the page
`@PhilComments/stopped.html.twig`, which says why, keeps the text sent in a read-only field to copy, and links back to
the page; an application dresses it by overriding the template under
`templates/bundles/PhilCommentsBundle/stopped.html.twig`. Every event of [threads](threads.md#events) is dispatched as
for the endpoints, so `phil/mentions-bundle` records the mentions of a comment sent by a plain form too.

## Mentions

With `phil/mentions-bundle` enabled and the block editor's routes imported, every text area of the section takes the
`mention` controller of that bundle, whose `@` autocomplete helps where JavaScript runs. Without it, the text areas stay
plain, and the handles typed are found again on the server all the same.

## The roles of WAI-ARIA 1.3

With the option `aria_roles`, each comment of the section is wrapped in an element of role `comment`, as in the panel
of the controller ([JavaScript](javascript.md#the-roles-of-wai-aria-13)).

## The labels

The labels live in the translation domain `phil_comments`, in English and French, under the keys `page.*`; an
application overrides one in its own `translations/phil_comments.<locale>` file.
