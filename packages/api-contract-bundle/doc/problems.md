# Problems

`ProblemDetailsListener` answers exceptions with RFC 9457 problem details, `application/problem+json`.

| The exception | Where | The answer |
|---|---|---|
| `ProblemException` | Anywhere | Its problem, its status, its headers |
| An HTTP exception (`NotFoundHttpException`, `AccessDeniedHttpException`…) | Under `problem_details.paths` | The blank problem of its status: `{"type": "about:blank", "status": 404, "title": "Not Found"}`, with the exception's headers |
| Any other exception | Under `problem_details.paths`, out of debug | A `500` without detail; in debug, Symfony's error page |

```php
use Phil\ApiContract\Problem\ProblemDetails;
use Phil\ApiContractBundle\Infrastructure\Http\ProblemException;

throw ProblemException::of(409, 'This order was already shipped.');

throw new ProblemException(
    ProblemDetails::create(429, 'https://example.com/problems/slow-down', 'Slow down', extensions: ['limit' => 10]),
    ['Retry-After' => '60'],
);
```

`detail` is read by the caller: never put an exception's message in it. `ProblemDetailsResponse` writes a problem
for a controller that returns one rather than throwing it.
