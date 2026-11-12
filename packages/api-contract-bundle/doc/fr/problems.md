<!-- phil-dev source-sha256=f6cc992c5afa36e2cffa9caaaf78ac3b0d0616337752513a5255a8033a01cbcb -->
# Problèmes

`ProblemDetailsListener` répond aux exceptions par des détails de problème RFC 9457, `application/problem+json`.

| L'exception | Où | La réponse |
|---|---|---|
| `ProblemException` | Partout | Son problème, son statut, ses en-têtes |
| Une exception HTTP (`NotFoundHttpException`, `AccessDeniedHttpException`…) | Sous `problem_details.paths` | Le problème vierge de son statut : `{"type": "about:blank", "status": 404, "title": "Not Found"}`, avec les en-têtes de l'exception |
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

L'appelant lit `detail` : n'y mettez jamais le message d'une exception. `ProblemDetailsResponse` écrit un problème pour
un contrôleur qui le retourne plutôt que de le lever.
