<!-- phil-dev source-sha256=d034f68584b95d4077127786cc288b631c7d45a18f508a9f54c91a5a1baa325b -->
# Détails de problème (RFC 9457)

**État : implémenté et testé.** Classe : `Phil\ApiContract\Problem\ProblemDetails`.

## La règle

Une API a **un seul format d'erreur** : la RFC 9457, type de média `application/problem+json`.

- **Le `type` est le contrat; le `detail` est du texte pour un humain.** Un client se branche sur `type` et, pour un
  `type` qu'il ne connaît pas, se replie sur `status`. Un nouveau `type` est donc un ajout.
- `detail` est écrit par le gestionnaire d'erreurs pour le lecteur de la réponse et n'est **jamais copié d'une
  exception** : aucune trace de pile, aucune requête, aucune valeur que l'appelant ne détient pas déjà. La bibliothèque
  ne peut pas l'imposer; elle garde `detail` comme une simple chaîne que l'application remplit délibérément.
- Les URI de `type` appartiennent à l'application. La bibliothèque n'en déclare aucune.

La classe a deux points d'entrée, un pour chaque côté du contrat : **le serveur écrit de façon stricte, le client lit
de façon tolérante.**

## Côté serveur : `create()`

```php
public static function create(
    int $status,
    string $type = ProblemDetails::BLANK_TYPE,   // 'about:blank'
    ?string $title = null,
    ?string $detail = null,
    ?string $instance = null,
    array $extensions = [],
): ProblemDetails
```

`create()` lève `InvalidContractValue` quand :

| Vérification | Accepté | Refusé |
|---|---|---|
| `status` est un statut d'erreur | de `400` à `599` inclusivement | `200`, `302`, `399`, `600` |
| `type` est `about:blank` ou une URI **absolue** (schéma, deux-points, aucune espace) | `about:blank`, `https://example.com/errors/out-of-credit` | `/errors/out-of-credit` (relative), `example.com/errors` (sans schéma), `''`, une URI avec une espace ou un saut de ligne à la fin |
| Aucune extension ne masque un membre standard | `balance` | `type`, `status`, `title`, `detail`, `instance` |
| Les noms d'extension suivent la recommandation de la RFC 9457 | une lettre, puis au moins deux lettres, chiffres ou traits de soulignement : `balance`, `retry_after` | `ab` (trop court), `1abc` (commence par un chiffre), `out-of` (trait d'union) |

La RFC 9457 permet un `type` relatif, résolu par rapport à la base du document. Un contrat stable ne devrait pas
dépendre de l'endroit d'où le document a été récupéré : la bibliothèque n'accepte donc qu'une URI absolue.

```php
use Phil\ApiContract\Problem\ProblemDetails;

$problem = ProblemDetails::create(
    403,
    'https://example.com/errors/out-of-credit',
    'Out of credit',
    'Your balance is 30.',
    '/account/12345/msgs/abc',
    ['balance' => 30],
);

$response = new JsonResponse($problem->toArray(), $problem->status, [
    'Content-Type' => ProblemDetails::MEDIA_TYPE,
]);
```

`toArray()` retourne les membres prêts à encoder, dans l'ordre `type`, `status`, `title`, `detail`, `instance`, puis
les extensions. **Un membre absent est omis plutôt qu'écrit comme `null`** :
`ProblemDetails::create(404)->toArray()` donne `['type' => 'about:blank', 'status' => 404]`.

## Côté client : `fromArray()`

```php
public static function fromArray(array $members, int $responseStatus): ProblemDetails
```

Passez le document JSON décodé et le statut HTTP de la réponse.

- **`status`** : le membre `status` l'emporte quand c'est un statut d'erreur entier, parce qu'un intermédiaire a pu
  changer le statut HTTP et que le membre dit ce que l'origine a envoyé (`['status' => 429]` sur un `502` se lit
  `429`). Sinon, le statut de la réponse est utilisé (`['status' => '429']` ou `['status' => 200]` sur un `503` se lit
  `503`). Si ni l'un ni l'autre n'est un statut d'erreur, le document n'est pas un problème et `InvalidContractValue`
  est levée.
- **`type`** : gardé quand c'est une chaîne qui vaut `about:blank` ou une URI absolue. Tout le reste (absent, pas une
  chaîne, relatif) devient `about:blank`, ce qui laisse le statut décider.
- **`title`, `detail`, `instance`** : gardés quand ce sont des chaînes, `null` autrement. Un membre du mauvais type
  JSON est ignoré, comme l'exige la RFC 9457 à la section 3.1.
- **Extensions** : chaque autre membre à clé de type chaîne est gardé tel quel, **même quand son nom ne suit pas la
  recommandation** (`ab` est gardé) : le client est tolérant. Les clés entières sont retirées.

```php
$problem = ProblemDetails::fromArray(json_decode($body, true, flags: JSON_THROW_ON_ERROR), $statusCode);

if ($problem->hasType('https://example.com/errors/out-of-stock')) {
    // the case this client knows
} elseif ($problem->isBlank() || $problem->status >= 500) {
    // nothing more specific than the status: fall back on it
}
```

Un document écrit par `create()` se relit à l'identique par `fromArray($problem->toArray(), $problem->status)`.

## API publique

| Membre | Description |
|---|---|
| `MEDIA_TYPE` | `'application/problem+json'` |
| `BLANK_TYPE` | `'about:blank'` |
| `$type`, `$status`, `$title`, `$detail`, `$instance`, `$extensions` | Propriétés publiques en lecture seule. |
| `create(...)` | Constructeur strict pour le serveur. |
| `fromArray(array $members, int $responseStatus)` | Lecteur tolérant pour le client. |
| `hasType(string $type): bool` | Comparaison exacte avec le `type`. |
| `isBlank(): bool` | Indique si le `type` est `about:blank`, c'est-à-dire si le client n'a rien de plus précis que le statut. |
| `toArray(): array` | Les membres, sans les membres absents. |

## Hors de la bibliothèque

- Aucun écouteur d'exception : le rendu revient à l'application, ou à `phil/api-contract-bundle` dans une application
  Symfony.
- Aucun catalogue d'URI de `type`.
- Aucune correspondance entre les exceptions de la bibliothèque et des problèmes : associez `InvalidContractValue`
  habituellement à `400`, selon ce que décide votre contrat.
