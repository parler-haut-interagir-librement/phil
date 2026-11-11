<!-- phil-dev source-sha256=fc67d2f6318b5f3b5432fac98e0aa985e14aac9f79b4f44b7c8573f4c7744308 -->
# Conscience de soi

`AsSelfAwareableEnumTrait` permet à du code qui reçoit une classe d'enum de demander de quelle sorte d'enum il s'agit.

```php
use Phil\Enums\Traits\AsSelfAwareableEnumTrait;

enum HttpStatus: int
{
    use AsSelfAwareableEnumTrait;

    case OK = 200;
    case NOT_FOUND = 404;
}

enum Direction
{
    use AsSelfAwareableEnumTrait;

    case NORTH;
    case SOUTH;
}
```

| Méthode | Vrai lorsque l'enum | `HttpStatus` | `Direction` |
|---|---|---|---|
| `isPure()` | n'a pas de type d'adossement | `false` | `true` |
| `isBacked()` | est adossée, à n'importe quel type | `true` | `false` |
| `isBackedByInteger()` | est adossée à `int` | `true` | `false` |
| `isBackedByString()` | est adossée à `string` | `false` | `false` |
