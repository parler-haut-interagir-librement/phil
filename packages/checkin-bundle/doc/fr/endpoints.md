<!-- phil-dev source-sha256=c360a1244af3ed550447b94e52c493ec7fbc0d0bf9f3bc85ad2c2c122d2a05b3 -->
# Points de terminaison

Chaque point de terminaison répond en JSON avec `Cache-Control: no-store, private`, et seulement :

1. quand `phil_checkin.enabled` est vrai — sinon 404 `{"error": "disabled"}` ;
2. au contrôleur du poste du site — son en-tête `X-Phil-Checkin: 1`, aucun `Sec-Fetch-Site: cross-site` et, pour un
   `POST`, le jeton CSRF sans état `phil_checkin` — sinon 403 `{"error": "forbidden"}` ;
3. sur un point de contrôle auquel la sécurité de l'application accorde `PHIL_CHECKIN_SCAN` — sinon 403
   `{"error": "forbidden"}`.

Une requête mal formée donne 400 `{"error": "invalid", "reason": "…"}` ; la raison ne répète jamais un code.

## `POST /scan`

```json
{"code": "T-1001-9f3a…", "checkpoint": "main-door", "device": "a3f9…", "direction": "in", "id": "0f8fad5b…"}
```

`direction` vaut `in` par défaut ; `id` est l'identifiant de la lecture, tiré par l'appareil pour qu'une nouvelle
tentative soit enregistrée une seule fois.

```json
{
    "outcome": "already_admitted",
    "replayed": false,
    "id": "0f8fad5b…",
    "credential": {"id": "ticket-4411", "label": "Élise Tremblay"},
    "attendance": {"entries": 1, "inside": true, "lastEntryAt": 1794132061}
}
```

`outcome` est l'un de `admitted`, `checked_out`, `already_admitted`, `not_inside`, `not_yet_valid`, `expired`,
`revoked`, `wrong_checkpoint`, `unknown` et `collision` ; un code inconnu n'a ni `id` ni `credential`. Une lecture
renvoyée sous son identifiant reçoit `replayed` seulement si c'est la même lecture ; une autre lecture sous un
identifiant déjà inscrit est une `collision`, sans `id` ni `credential`, et l'appareil la renvoie sous un nouvel
identifiant. Quand le registre reste
occupé sous des lectures du même titre d'accès, la réponse est 503 `{"error": "busy"}`, et l'appareil renvoie la
lecture sous le même identifiant.

## `GET /manifest?checkpoint=…&device=…`

Le manifeste hors ligne du point de contrôle pour l'appareil, tel que le donne `OfflineManifest::toArray()` ; voyez
le `doc/offline.md` de `phil/checkin`. Il répond 404 sans catalogue, 413 `{"error": "too_large"}` quand le
catalogue contient plus de titres d'accès que `offline.max_credentials`, et 409 `{"error": "weak_codes"}` quand il
contient un code sous `offline.min_code_bits`.

## `POST /sync`

```json
{"checkpoint": "main-door", "device": "a3f9…", "scans": [{"id": "…", "credential": "ticket-4411", "direction": "in", "outcome": "admitted", "scannedAt": 1794132061}]}
```

```json
{"scans": [{"id": "…", "status": "recorded"}]}
```

Chaque statut est `recorded`, `conflict`, `duplicate`, `collision` ou `rejected`. Un journal plus long que 
`offline.batch_limit`, ou qui contient une lecture mal formée, est refusé en entier avec 400. Il répond 404 sans
catalogue.
