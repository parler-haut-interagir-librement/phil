<!-- phil-dev source-sha256=8346d12dbaa031344c24c11a162e397238c176718a34a8d1174e526bc8d9032f -->
# Problèmes

`ProblemDetailsListener` répond aux exceptions par des détails de problème RFC 9457, `application/problem+json`.

| L'exception | Où | La réponse |
|---|---|---|
| `ProblemException` | Partout | Son problème, son statut, ses en-têtes |
| Une exception HTTP (`NotFoundHttpException`, `AccessDeniedHttpException`…) | Sous `problem_details.paths` | Le problème vierge de son statut : `{"type": "about:blank", "status": 404, "title": "Not Found"}`, avec les en-têtes de l'exception |
| `OptimisticLockException` de Doctrine, levée ou enveloppée jusqu'à huit niveaux de profondeur — par la `HandlerFailedException` de Messenger, ou déballée par un bus avec `unwrap_exceptions` | Sous `problem_details.paths`, en mode débogage aussi | Une `409` du type stable `urn:phil:problem:write-conflict` (`ProblemDetailsListener::WRITE_CONFLICT_TYPE`), sans l'entité ni ses versions |
| Toute autre exception | Sous `problem_details.paths`, hors du mode débogage | Une `500` sans détail ; en mode débogage, la page d'erreur de Symfony |

```php
use Phil\ApiContract\Problem\ProblemDetails;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemException;

throw ProblemException::of(409, 'This order was already shipped.');

throw new ProblemException(
    ProblemDetails::create(429, 'https://example.com/problems/slow-down', 'Slow down', extensions: ['limit' => 10]),
    ['Retry-After' => '60'],
);
```

## Conflits d'écriture

Une entité dotée d'une colonne `#[ORM\Version]` refuse un `flush` sur une version que quelqu'un d'autre a modifiée entre-temps, par une
`OptimisticLockException`. Sous les chemins, l'appelant reçoit :

```json
{"type": "urn:phil:problem:write-conflict", "status": 409, "title": "The resource changed since it was read.", "detail": "Read it again, then send the change again."}
```

Un client teste le `type`, qui reste le même d'une version à l'autre, plutôt que le titre. `ETag` et `If-Match` sont
laissés aux routes qui modifient une ressource.

## Détails

L'appelant lit `detail` : n'y mettez jamais le message d'une exception. `ProblemDetailsResponse` écrit un problème pour
un contrôleur qui le retourne plutôt que de le lever.
