# Endpoints

Every endpoint answers JSON with `Cache-Control: no-store, private`, and only:

1. when `phil_checkin.enabled` is true — otherwise 404 `{"error": "disabled"}`;
2. to the desk controller of the site — its header `X-Phil-Checkin: 1`, no `Sec-Fetch-Site: cross-site`, and for a
   `POST` the stateless CSRF token `phil_checkin` — otherwise 403 `{"error": "forbidden"}`;
3. on a checkpoint the application's security grants `PHIL_CHECKIN_SCAN` — otherwise 403 `{"error": "forbidden"}`.

A malformed request is 400 `{"error": "invalid", "reason": "…"}`; the reason never repeats a code.

## `POST /scan`

```json
{"code": "T-1001-9f3a…", "checkpoint": "main-door", "device": "a3f9…", "direction": "in", "id": "0f8fad5b…"}
```

`direction` is `in` by default; `id` is the scan's identifier, drawn by the device so that a retry is recorded once.

```json
{
    "outcome": "already_admitted",
    "replayed": false,
    "id": "0f8fad5b…",
    "credential": {"id": "ticket-4411", "label": "Élise Tremblay"},
    "attendance": {"entries": 1, "inside": true, "lastEntryAt": 1794132061}
}
```

`outcome` is one of `admitted`, `checked_out`, `already_admitted`, `not_inside`, `not_yet_valid`, `expired`,
`revoked`, `wrong_checkpoint`, `unknown` and `collision`; an unknown code has no `id` nor `credential`. A scan sent
again under its identifier is answered `replayed` only when it is the same scan; another scan under an identifier
already recorded is a `collision`, without `id` nor `credential`, and the device sends it again under a new
identifier. When the ledger stays busy
under scans of the same credential, the answer is 503 `{"error": "busy"}`, and the device sends the scan again under
the same identifier.

## `GET /manifest?checkpoint=…&device=…`

The offline manifest of the checkpoint for the device, as `OfflineManifest::toArray()` gives it; see
`phil/checkin`'s `doc/offline.md`. It answers 404 without a catalog, 413 `{"error": "too_large"}` when the
catalog holds more credentials than `offline.max_credentials`, and 409 `{"error": "weak_codes"}` when it holds a code
under `offline.min_code_bits`.

## `POST /sync`

```json
{"checkpoint": "main-door", "device": "a3f9…", "scans": [{"id": "…", "credential": "ticket-4411", "direction": "in", "outcome": "admitted", "scannedAt": 1794132061}]}
```

```json
{"scans": [{"id": "…", "status": "recorded"}]}
```

Each status is `recorded`, `conflict`, `duplicate`, `collision` or `rejected`. A journal longer than
`offline.batch_limit`, or with a malformed scan, is refused whole with 400. It answers 404 without a catalog.
