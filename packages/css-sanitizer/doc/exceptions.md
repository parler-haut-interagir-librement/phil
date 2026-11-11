# Exceptions

Every exception implements `Phil\CssSanitizer\Exception\CssSanitizerException`, and each extends
`InvalidArgumentException`.

| Exception | Thrown when |
|---|---|
| `InvalidPolicy` | a policy is asked to allow a property or function that is never allowed, or is given a maximum length or depth below 1 |
| `NestingTooDeep` | `Syntax\Parser`, used on its own, meets blocks and functions nested deeper than its limit |

The messages name what is wrong:

```
The property "behavior" can run code or load a binding; it is never allowed.
The function "image-set()" can run code or load a resource; it is never allowed.
The maximum depth must be at least 1; got 0.
The CSS nests blocks and functions deeper than 32 levels, on line 4.
```

`CssSanitizer` never throws on the CSS it reads: it catches `NestingTooDeep` and returns a `TooDeep` removal.
