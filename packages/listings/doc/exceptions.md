# Exceptions

Every exception of the library implements `Phil\Listings\Exception\ListingsException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidListing` | `InvalidArgumentException` | A value the library cannot work with: an identifier, a key, a title, a place, a criterion, a policy's bound, a minimum age, a stored listing, or a policy of another kind than the listing's. |
| `ForbiddenTransition` | `LogicException` | A listing or a response asked for a change its status does not allow; a report by the author or on a listing that is not visible; an extension past the kind's last. |
| `UnknownKind` | `RuntimeException` | `KindPolicies::get()` asked for a kind no policy is declared for. |
| `ResponseRefused` | `RuntimeException` | The `Responder` refused a response or an acceptance; its public `refusal` is the `Refusal` that says why. |

The messages name the rule that failed, never the value a member typed: a refused title says how long a title may be,
not what it said.

```php
use Phil\Listings\Exception\ResponseRefused;

try {
    $responder->respond($listing, $id, $member);
} catch (ResponseRefused $refused) {
    return $this->render('listing/refused.html.twig', ['reason' => $refused->refusal->value]);
}
```
