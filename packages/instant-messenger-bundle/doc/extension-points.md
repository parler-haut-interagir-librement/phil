# Extension points

What an application adds or extends. The services and handles are `phil/instant-messenger`'s.

## The application's own services

An enum of the application's implementing `Phil\InstantMessenger\MessengerType` is accepted wherever services are
named: in the `types` of `HandleType` and of `HandleOfType`, before or after the international ones, as an enum
class, a case, an `EnumSet` or a list of these. Its choices are named by their case's name; `messenger_label` gives
the same — see [Forms and validation](forms-and-validation.md).

## Forms and validation

| Point | How |
|---|---|
| The services offered, and their order | The `types` option of `HandleType` |
| The services accepted | The `types` of `HandleOfType`; its `message` and the code `HandleOfType::NOT_ACCEPTED_ERROR` |
| More than the field does | A form type extension of the application's on `HandleType` |

## Storage and Twig

| Point | How |
|---|---|
| Where a handle is kept | Two columns the application maps — the service with its own enum, the canonical handle — read back with `Handle::fromCanonical()` |
| How a handle is shown | `messenger_display`, `messenger_link` and `messenger_label` — see [Twig](twig.md) |
