# Tolerant reading

**State:** `TolerantEnum` and `UnknownAuthorityValue` are implemented and tested.

## The rule

A contract that grows by addition obliges every client to read tolerantly:

1. **Ignore unknown fields.** A new field is not a breaking change. This half is the deserializer's concern, not
   the library's: configure your serializer to ignore extra attributes, and **never validate a response against a
   closed schema**.
2. **Map unknown enumeration values to a default.** A new enumeration value is not a breaking change either, so a
   client meets values it does not know. The library carries this half, with two rules chosen per field:
   - an **ordinary field** takes the default the contract sets for it;
   - **a field of authority refuses**: an unknown assurance level satisfies no requirement, an unknown grant
     state grants nothing, an unknown security event type is not processed.

## `TolerantEnum`

`Phil\ApiContract\TolerantReading\TolerantEnum`, static methods over any `BackedEnum`:

```php
/** @template T of BackedEnum  @param class-string<T> $enum  @param T $default  @return T */
public static function withDefault(string $enum, mixed $value, BackedEnum $default): BackedEnum

/** @template T of BackedEnum  @param class-string<T> $enum  @return T  @throws UnknownAuthorityValue */
public static function authoritative(string $enum, mixed $value): BackedEnum
```

Both look the value up among `$enum::cases()` with **strict comparison and no coercion**: a JSON value of the
wrong type is as unknown as a string the enumeration does not list. `"1"` is not `1`, `"STRONG"` is not
`"strong"`, `null` and arrays are unknown.

```php
use Phil\ApiContract\TolerantReading\TolerantEnum;

enum Priority: int { case Normal = 1; case High = 2; }
enum AssuranceLevel: string { case Basic = 'basic'; case Strong = 'strong'; }

$priority = TolerantEnum::withDefault(Priority::class, $data['priority'] ?? null, Priority::Normal);
// 2 -> Priority::High; '2', 7, null -> Priority::Normal

$level = TolerantEnum::authoritative(AssuranceLevel::class, $data['acr'] ?? null);
// 'strong' -> AssuranceLevel::Strong; 'phishing-resistant' -> UnknownAuthorityValue
```

Catch `UnknownAuthorityValue` where the requirement is checked, and treat it as "not satisfied".

## `UnknownAuthorityValue`

`Phil\ApiContract\Exception\UnknownAuthorityValue`, an `UnexpectedValueException` that implements
`ApiContractException`. Its message names the value and the enumeration:

```text
"phishing-resistant" is not a known value of App\AssuranceLevel; an unknown authority value is refused.
```

The value comes from a response and is not trusted, so the message echoes it **bounded and escaped**:

- an integer is shown as is (`7 is not a known value of ...`);
- a string is quoted, cut to its first 64 bytes followed by `...` when longer, with control characters, quotes,
  backslashes and bytes above `0x7E` escaped (no raw newline reaches a log line);
- any other type is shown by its type name (`array is not a known value of ...`, `null is not ...`).

## Not in the library

Serializer configuration, and any list of which fields of your contract are authoritative: that list belongs to
the application.
