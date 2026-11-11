# Endpoints

Every endpoint answers only a request of the controller. It answers `404` until the application has configured
`holder_provider` and `policy`, and `401` when the holder provider names nobody. Bodies are JSON.

`EditLockRequest` tells a request of the controller by three checks:

- the header `X-Phil-Edit-Lock: 1`, which a form or a link of another site cannot add without the browser asking
  first;
- no `Sec-Fetch-Site: cross-site` from the browser;
- for a `POST`, Symfony's stateless CSRF check of the token `phil_edit_lock`; the `GET` of the presence changes
  nothing and carries no token. For each `POST` the controller draws a new random
  token, sets it in a cookie named after `csrfCookie` (`csrf-token` by default, FrameworkBundle's
  `csrf_protection.cookie_name`; `__Host-` first and `secure` over HTTPS, same-site only) and sends it in a header of
  the same name. `SameOriginCsrfTokenManager` accepts the request when the browser's `Sec-Fetch-Site`, `Origin` or
  `Referer` names this site, or when the header and the cookie carry the same token, and refuses it when either says
  otherwise; FrameworkBundle clears the cookie on the answer.

| Route | Method, path | Body | Success |
|---|---|---|---|
| `phil_edit_lock_acquire` | `POST /acquire` | `{"resource", "part"}` | `200 {"lease": …}` |
| `phil_edit_lock_heartbeat` | `POST /heartbeat` | `{"resource", "part"}` | `200 {"lease": …}` |
| `phil_edit_lock_release` | `POST /release` | `{"resource", "part"}` | `204` |
| `phil_edit_lock_take_over` | `POST /take-over` | `{"resource", "part"}` | `200 {"lease": …}` |
| `phil_edit_lock_leave` | `POST /leave` | `{"resource"}` | `204` |
| `phil_edit_lock_presence` | `GET /presence?resource=…` | — | `200 {"leases": [ … ]}` |
| `phil_edit_lock_save` | `POST /save` | `{"resource", "block", "list", "after"}` | `204` |
| `phil_edit_lock_remove` | `POST /remove` | `{"resource", "id"}` | `204` |

`part` is optional, `null` for the whole resource; `list` defaults to `root` and `after` to `null`. A lease is
answered as:

```json
{"resource": "article:42", "part": "aB3x", "holder": {"id": "7", "name": "Ada"}, "expiresIn": 30, "mine": true}
```

## Errors

| Status | Body | When |
|---|---|---|
| `400` | `{"error": "invalid", "message"}` | JSON that cannot be read, a key, a block or a place that cannot be one |
| `401` | `{"error": "anonymous"}` | Nobody writes |
| `403` | `{"error": "forbidden"}` | The controller's header or CSRF token is missing or wrong, or the request comes from another site |
| `403` | `{"error": "refused"}` | The policy refuses to let the holder edit, or take over |
| `404` | `{"error": "disabled"}` | The application has not configured its holder provider and its policy |
| `409` | `{"error": "held", "lease"}` | Another holder holds the key; `lease` is theirs, or `null` after a race |
| `409` | `{"error": "lost"}` | The holder no longer holds the key, for a heartbeat or a save |
