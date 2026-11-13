# Endpoints

Imported from `config/routes.php` under the application's prefix, `/_comments` here. Every endpoint answers only a
request of the controller, from an author the application's `AuthorProvider` names, once the application has
configured its author provider and its policy. Bodies and answers are JSON.

`CommentsRequest` tells a request of the controller by three checks:

- the header `X-Phil-Comments: 1`, which a form or a link of another site cannot add without the browser asking
  first;
- no `Sec-Fetch-Site: cross-site` from the browser;
- for a `POST`, Symfony's stateless CSRF check of the token `phil_comments`; the `GET` of the threads changes
  nothing and carries no token. For each `POST` the controller draws a new random
  token, sets it in a cookie named after `csrfCookie` (`csrf-token` by default, FrameworkBundle's
  `csrf_protection.cookie_name`; `__Host-` first and `secure` over HTTPS, same-site only) and sends it in a header of
  the same name. `SameOriginCsrfTokenManager` accepts the request when the browser's `Sec-Fetch-Site`, `Origin` or
  `Referer` names this site, or when the header and the cookie carry the same token, and refuses it when either says
  otherwise; FrameworkBundle clears the cookie on the answer.

| Route | Method and path | Body | Answer |
|---|---|---|---|
| `phil_comments_threads` | `GET /threads?subject=article:42` | — | `200 {"threads": [thread, …]}` |
| `phil_comments_open` | `POST /threads` | `{"subject", "anchor", "body", "suggestion"}` | `201 {"thread"}` |
| `phil_comments_reply` | `POST /threads/{thread}/reply` | `{"body"}` | `201 {"thread"}` |
| `phil_comments_resolve` | `POST /threads/{thread}/resolve` | — | `200 {"thread"}` |
| `phil_comments_reopen` | `POST /threads/{thread}/reopen` | — | `200 {"thread"}` |
| `phil_comments_accept` | `POST /threads/{thread}/accept` | — | `200 {"thread"}` |
| `phil_comments_reject` | `POST /threads/{thread}/reject` | — | `200 {"thread"}` |
| `phil_comments_edit` | `POST /comments/{comment}/edit` | `{"body"}` | `200 {"thread"}` |
| `phil_comments_delete` | `POST /comments/{comment}/delete` | — | `200 {"thread"}`, `{"thread": null}` when the thread went with its last comment |

The plain forms of the comments on a page post to one more route, imported from `config/page_routes.php`:
`phil_comments_page`, `POST /page`, at a signed address, answered `303` back to the page; see [the page](page.md).

`anchor` is `{"block", "field", "start", "quote", "prefix", "suffix"}`: `block` and `field` texts or `null`, `start` a
whole number, `quote` a text, `prefix` and `suffix` texts or left out. An `anchor` that is `null` or left out opens a
thread on the whole page, which takes no `suggestion`. `suggestion` is a text — empty to propose a removal — or
`null`. Identifiers in paths are 1 to 36 letters, digits, `_` or `-`.

## A thread

```json
{
    "id": "0192f3c4-7d1e-7a40-9c4f-2b6f0e8d1a55",
    "subject": "article:42",
    "anchor": {"block": "a1B2c3", "field": "text", "start": 4, "end": 7, "quote": "cat", "prefix": "the ", "suffix": " sat"},
    "status": "open",
    "detached": false,
    "suggestion": {"text": "dog", "state": "pending"},
    "author": {"ref": "user:ada", "name": "Ada"},
    "createdAt": "2026-11-06T10:00:00+00:00",
    "resolvedBy": null,
    "resolvedAt": null,
    "may": {"resolve": true, "decide": false, "reply": true},
    "comments": [
        {"id": "…", "author": {"ref": "user:ada", "name": "Ada"}, "body": "A dog?", "createdAt": "…", "editedAt": null, "mine": true, "may": true}
    ]
}
```

A thread on the whole page has `"anchor": null`, `"detached": false` and `"suggestion": null`. `detached` is `true`
for an orphaned thread, whose passage was found nowhere the last time the application re-anchored the resource.

`may` says what the viewer may do, by the policy: resolve and reopen, decide on the suggestion, reply; on a comment,
edit and delete it.

## Errors

| Status | `error` | When |
|---|---|---|
| 400 | `invalid`, with `message` | A body that is not JSON, a field of the wrong type, a reference, an anchor, a comment or a suggestion that cannot be kept, a suggestion without a passage, an author named by an email address, a decision on a thread without a pending suggestion |
| 401 | `anonymous` | The author provider names nobody |
| 403 | `forbidden` | The header or the CSRF token is missing or wrong, or the request comes from another site |
| 403 | `refused` | The policy does not allow it |
| 404 | `missing` | No thread or comment has this identifier |
| 404 | `disabled` | The author provider or the policy is not configured |

## The controller's configuration

`ClientConfig::for($subject, $viewer, $page = true)` gives the controller what it needs for one page: the subject, the
viewer's reference, the endpoints' URLs, those of one thread or comment with `__id__` in place of its identifier,
`csrfCookie`, the name of the cookie and header that carry its CSRF token, `mentions`, the address of the member
search when `phil/mentions-bundle` is enabled and its route imported, `null` otherwise, and `page`, whether the panel
offers a form to comment on the whole page.

```php
return $this->render('article/edit.html.twig', [
    'comments' => $clientConfig->for(ResourceReference::of('article', (string) $article->id()), $currentAuthor),
]);
```
