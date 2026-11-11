<!-- phil-dev source-sha256=cdfe3ab2ba5d8f0f30adff0a75eb33383fc07d0107dd7cd47a81873f647b6e76 -->
# Exceptions

Chaque exception implémente `Phil\CssSanitizer\Exception\CssSanitizerException`, et chacune étend
`InvalidArgumentException`.

| Exception | Levée quand |
|---|---|
| `InvalidPolicy` | on demande à une politique de permettre une propriété ou une fonction qui n'est jamais permise, ou on lui donne une longueur ou une profondeur maximale inférieure à 1 |
| `NestingTooDeep` | `Syntax\Parser`, utilisé seul, rencontre des blocs et des fonctions imbriqués plus profondément que sa limite |

Les messages nomment ce qui ne va pas :

```
The property "behavior" can run code or load a binding; it is never allowed.
The function "image-set()" can run code or load a resource; it is never allowed.
The maximum depth must be at least 1; got 0.
The CSS nests blocks and functions deeper than 32 levels, on line 4.
```

`CssSanitizer` ne lève jamais d'exception sur le CSS qu'il lit : il attrape `NestingTooDeep` et renvoie un retrait
`TooDeep`.
