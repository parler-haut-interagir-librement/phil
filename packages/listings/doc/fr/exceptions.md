<!-- phil-dev source-sha256=7da8183c1b7797ba2b506839c91ff32e0d9923fb0c2fc55679b6db83e83dcbd9 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\Listings\Exception\ListingsException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidListing` | `InvalidArgumentException` | Une valeur que la bibliothèque ne peut pas utiliser : un identifiant, une clé, un titre, un lieu, un critère, une borne de politique, un âge minimal, une annonce stockée, ou la politique d'un autre genre que celui de l'annonce. |
| `ForbiddenTransition` | `LogicException` | Une annonce ou une réponse a demandé un changement que son statut ne permet pas ; un signalement par l'auteur ou sur une annonce qui n'est pas visible ; une prolongation au-delà de la dernière permise par le genre. |
| `UnknownKind` | `RuntimeException` | `KindPolicies::get()` a demandé un genre pour lequel aucune politique n'est déclarée. |
| `ResponseRefused` | `RuntimeException` | Le `Responder` a refusé une réponse ou une acceptation ; sa propriété publique `refusal` est le `Refusal` qui dit pourquoi. |

Les messages nomment la règle qui a échoué, jamais la valeur qu'a tapée un membre : un titre refusé indique la longueur
permise d'un titre, pas ce qu'il disait.

```php
use Phil\Listings\Exception\ResponseRefused;

try {
    $responder->respond($listing, $id, $member);
} catch (ResponseRefused $refused) {
    return $this->render('listing/refused.html.twig', ['reason' => $refused->refusal->value]);
}
```
