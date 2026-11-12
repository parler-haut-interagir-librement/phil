# Endpoints

One path per draft, `/drafts/{key}` under the prefix the application imports `config/routes.php` with, the key being
16 to 64 URL-safe characters. The key is a keyed hash: no personal data travels in the URL.

| Route | Method | Body | Answers |
|---|---|---|---|
| `phil_forms_autosave_draft` | `GET` | | `200` `{"revision", "savedAt", "version", "fields"}`, or `204` when none is kept |
| `phil_forms_autosave_draft_save` | `PUT` | `{"revision", "version", "fields"}` | `200` `{"revision"}`; `409` `{"error", "revision"}`; `413`; `400` |
| `phil_forms_autosave_draft_discard` | `DELETE` | | `204` |

`revision` is the revision the browser read, or null; `fields` is an object of lists of texts, by field name. Every
answer carries `Cache-Control: no-store, private`.

## Who is answered

An endpoint answers `403` unless the request:

- carries the header `X-Phil-Forms-Autosave: 1`, which a form or a link of another site cannot add;
- is not marked `Sec-Fetch-Site: cross-site`;
- for `PUT` and `DELETE`, passes Symfony's stateless CSRF check of the token `phil_forms_autosave`: a new token in a
  header named by the cookie the form gives the controller, and in a cookie of that name, or the browser's
  `Sec-Fetch-Site`, `Origin` or `Referer` naming this site;
- comes from a user `DraftOwner` names.

The routes still sit behind the application's firewall; a rate limiter of the application can sit in front of them.
