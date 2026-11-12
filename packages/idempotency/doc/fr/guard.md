<!-- phil-dev source-sha256=63d19aa8cde802a3885993209600db7256c4134b871f5bbbf59324d79f7d7394 -->
# La garde

`IdempotencyGuard` exécute la séquence du stockage : trouver, décider, réserver, puis terminer ou échouer. Elle ne
garde aucun état entre deux appels : une seule instance sert chaque requête d'un processus de longue durée.

```php
new IdempotencyGuard(IdempotencyStore $store, ClockInterface $clock, int $retention = 86_400, int $reservationTtl = 300)
```

- `$retention` : combien de secondes une clé répondue ou échouée est gardée. Elle doit **durer plus longtemps que
  toute panne plausible**, sinon la nouvelle tentative que la clé existe pour absorber arrive après l'oubli de la clé
  et s'exécute deux fois.
- `$reservationTtl` : combien de secondes une clé reste en cours avant sa réponse, ou la rétention si elle est plus
  courte. Gardez-la plus longue que l'exécution la plus lente : une réservation qui expire pendant que son exécution
  se poursuit laisse une nouvelle tentative s'exécuter à côté.

Les deux valent au moins 1 seconde. Chaque méthode prend un `$retention` facultatif qui remplace celui de la garde pour
une clé.

## `run()`, autour d'une opération

```php
/** @param Closure(): StoredResponse $operation */
public function run(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, Closure $operation, ?int $retention = null): IdempotentRun
```

| Issue | Ce que fait `run()` |
|---|---|
| `Proceed` | Exécute `$operation`, garde sa réponse, la retourne avec `replayed: false`. Une exception marque la clé échouée et est relancée. |
| `Replay` | Retourne la réponse stockée avec `replayed: true`, sans rien exécuter. |
| `Reused` | Lève `IdempotencyKeyReused`. |
| `InProgress` | Lève `RequestInProgress`, dont `retryAfter` donne les secondes qui restent à la réservation. |

## `begin()`, `complete()`, `fail()`, pour un début et une fin vus séparément

Un écouteur qui voit la requête et la réponse en deux événements sépare l'exécution :

```php
$decision = $guard->begin($scope, $key, $fingerprint);

match ($decision->outcome) {
    IdempotencyOutcome::Replay => $response = $decision->replay(),
    IdempotencyOutcome::Reused => throw IdempotencyKeyReused::withAnotherRequest(),
    IdempotencyOutcome::InProgress => throw $guard->inProgress($decision),
    IdempotencyOutcome::Proceed => null, // reserved: run, then complete() or fail()
};
```

`begin()` réserve sur `Proceed`. Quand une autre exécution prend la clé entre la lecture et la réservation, elle relit
et décide selon ce qui l'a prise — un rejeu, une réutilisation, une exécution en cours. Après trois réservations
perdues, elle répond `InProgress`. `inProgress($decision)` construit l'exception avec les secondes qui restent à
l'enregistrement.

## Une API

```php
try {
    $run = $guard->run(
        IdempotencyScope::of('client:'.$clientId),
        IdempotencyKey::fromHeader($request->headers->get(IdempotencyKey::HEADER)),
        PayloadFingerprint::ofRequest($request->getMethod(), $request->getRequestUri(), $request->getContent()),
        static fn (): StoredResponse => StoredResponse::of(201, ['Location' => ['/orders/7']], $body),
    );
} catch (MissingIdempotencyKey|InvalidIdempotencyValue) {
    // 400 problem
} catch (IdempotencyKeyReused) {
    // 422 problem
} catch (RequestInProgress $inProgress) {
    // 409 problem, Retry-After: $inProgress->retryAfter
}
```

Un `5xx` que le client peut réessayer est un échec : levez une exception depuis l'opération, ou appelez `fail()` après
`begin()`. Un `4xx` est une réponse comme une autre, et il est rejoué.

## Un webhook de paiement

Un fournisseur de paiement envoie chaque événement au moins une fois. L'identifiant de son événement est la clé, son
expéditeur la portée ; l'empreinte couvre le type d'événement et l'objet visé, pas l'horodatage de la livraison :

```php
$guard->run(
    IdempotencyScope::of('webhook:payments'),
    IdempotencyKey::fromString($event->id),
    PayloadFingerprint::ofParts($event->type, $event->objectId),
    static function () use ($event): StoredResponse {
        $payments->apply($event);

        return StoredResponse::nothing();
    },
);
```

Vérifiez la signature du webhook avant la garde : un événement non signé ne doit pas prendre de clé.

## Un formulaire envoyé deux fois

Rendez une clé dans un champ caché, `IdempotencyKey::generate()->value`, avec le formulaire. À la soumission, la
session est la portée et les champs soumis l'empreinte ; un second clic rejoue la redirection du premier :

```php
$run = $guard->run(
    IdempotencyScope::of('session:'.$session->getId()),
    IdempotencyKey::fromString($form->get('_idempotency_key')->getData()),
    PayloadFingerprint::ofParts('contact', $data->email, $data->message),
    static fn (): StoredResponse => StoredResponse::of(303, ['Location' => ['/thanks']]),
    retention: 3_600,
);
```

Le jeton CSRF du formulaire n'est pas une clé : il est partagé par chaque formulaire d'une session.

## Messenger

Voir [Messenger](messenger.md) : un intergiciel exécute la garde autour des gestionnaires d'un message reçu.

Ensuite : [stockages](stores.md).
