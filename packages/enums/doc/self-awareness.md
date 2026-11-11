# Self-awareness

`AsSelfAwareableEnumTrait` lets code that receives an enum class ask what kind of enum it is.

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

| Method | True when the enum | `HttpStatus` | `Direction` |
|---|---|---|---|
| `isPure()` | has no backing type | `false` | `true` |
| `isBacked()` | is backed, by any type | `true` | `false` |
| `isBackedByInteger()` | is backed by `int` | `true` | `false` |
| `isBackedByString()` | is backed by `string` | `false` | `false` |
