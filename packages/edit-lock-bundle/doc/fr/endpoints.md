<!-- phil-dev source-sha256=ab71094052db463f4692de688b1c34bdb6443838d12629096b06711842cc1f39 -->
# Points de terminaison

Chaque point de terminaison ne répond qu'à une requête du contrôleur. Il répond `404` tant que l'application n'a pas
configuré `holder_provider` et `policy`, et `401` lorsque le fournisseur de détenteur ne nomme personne. Les corps sont
en JSON.

`EditLockRequest` reconnaît une requête du contrôleur par trois vérifications :

- l'en-tête `X-Phil-Edit-Lock: 1`, qu'un formulaire ou un lien d'un autre site ne peut pas ajouter sans que le
  navigateur demande d'abord la permission ;
- aucun `Sec-Fetch-Site: cross-site` de la part du navigateur ;
- pour un `POST`, la vérification CSRF sans état de Symfony du jeton `phil_edit_lock` ; le `GET` de la présence ne
  change rien et ne porte aucun jeton. Pour chaque `POST`, le contrôleur tire un nouveau jeton aléatoire, le place dans
  un témoin nommé d'après `csrfCookie` (`csrf-token` par défaut, le `csrf_protection.cookie_name` de FrameworkBundle ;
  préfixé de `__Host-` et `secure` en HTTPS, même site seulement) et l'envoie dans un en-tête du même nom.
  `SameOriginCsrfTokenManager` accepte la requête lorsque le `Sec-Fetch-Site`, l'`Origin` ou le `Referer` du navigateur
  nomme ce site, ou lorsque l'en-tête et le témoin portent le même jeton, et la refuse lorsque l'un d'eux dit autre
  chose ; FrameworkBundle efface le témoin dans la réponse.

| Route | Méthode, chemin | Corps | Succès |
|---|---|---|---|
| `phil_edit_lock_acquire` | `POST /acquire` | `{"resource", "part"}` | `200 {"lease": …}` |
| `phil_edit_lock_heartbeat` | `POST /heartbeat` | `{"resource", "part"}` | `200 {"lease": …}` |
| `phil_edit_lock_release` | `POST /release` | `{"resource", "part"}` | `204` |
| `phil_edit_lock_take_over` | `POST /take-over` | `{"resource", "part"}` | `200 {"lease": …}` |
| `phil_edit_lock_leave` | `POST /leave` | `{"resource"}` | `204` |
| `phil_edit_lock_presence` | `GET /presence?resource=…` | — | `200 {"leases": [ … ]}` |
| `phil_edit_lock_save` | `POST /save` | `{"resource", "block", "list", "after"}` | `204` |
| `phil_edit_lock_remove` | `POST /remove` | `{"resource", "id"}` | `204` |

`part` est facultatif, `null` pour la ressource entière ; `list` vaut `root` par défaut et `after` vaut `null`. Un bail
est renvoyé ainsi :

```json
{"resource": "article:42", "part": "aB3x", "holder": {"id": "7", "name": "Ada"}, "expiresIn": 30, "mine": true}
```

## Erreurs

| Statut | Corps | Quand |
|---|---|---|
| `400` | `{"error": "invalid", "message"}` | Du JSON illisible, ou une clé, un bloc ou un emplacement qui ne peut pas en être un |
| `401` | `{"error": "anonymous"}` | Personne n'écrit |
| `403` | `{"error": "forbidden"}` | L'en-tête ou le jeton CSRF du contrôleur manque ou est erroné, ou la requête vient d'un autre site |
| `403` | `{"error": "refused"}` | La politique refuse de laisser le détenteur modifier, ou reprendre |
| `404` | `{"error": "disabled"}` | L'application n'a pas configuré son fournisseur de détenteur et sa politique |
| `409` | `{"error": "held", "lease"}` | Un autre détenteur détient la clé ; `lease` est son bail, ou `null` après une course |
| `409` | `{"error": "lost"}` | Le détenteur ne détient plus la clé, pour une pulsation ou un enregistrement |
