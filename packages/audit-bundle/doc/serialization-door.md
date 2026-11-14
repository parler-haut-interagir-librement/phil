# The serialization door

**State: port only, on purpose.** `Phil\AuditBundle\Domain\Contract\CanonicalSerializer` is defined; the bundle
ships **no implementation**.

## A one-way door

A row's hash is taken over a canonical serialization of its fields. The field order and the encoding must be
fixed: a later change silently breaks the verification of every row written before it, and that failure cannot be
told apart from tampering.

**The door closes at the first anchored row.** Before it, the serialization can still change at no cost; after it,
every anchor commits to bytes produced by that exact serialization.

In a shared bundle the door closes **for each application that installs it, at its own first anchored row**.
Changing the bundle's implementation afterwards would turn every anchor of every such application into a
permanent discontinuity. That is why:

- **the bundle ships no serialization yet.** It ships the port alone. An application that anchors before the
  bundle ships one writes its own implementation, freezes it, and keeps it;
- **when an implementation enters the bundle, it comes with a version identifier written into each row**, and any
  later serialization is another implementation beside it, never a modification of the first.

## Versioned, never replaced

Each row records the version of the serialization it was hashed under, and the row hash covers that version.
Verification hashes the payload as it was stored and never serializes again, so rows already written keep
verifying whatever serializer the application ships later. The version is there for the reader of a payload, who
must know which encoding it is in, and for the application's own serializer registry.

## The port

```php
interface CanonicalSerializer
{
    /** @return non-empty-string stable for the life of the serializer: changing it is a new version */
    public function version(): string;

    /**
     * @param array<string, scalar|null> $fields the non-personal fields and the commitments, by name
     * @return string stable bytes: same fields, same bytes, always
     */
    public function serialize(array $fields): string;
}
```

Personal fields enter only through their `Commitment` bytes, never in plaintext.

## Decisions an implementation must freeze

- **Field order**: sorted by name, or a fixed list — never the order the array happens to arrive in.
- **Field set**: what happens to a missing field, an extra field, a `null`.
- **Scalar encoding**: integers, floats (avoid them, or fix their textual form), booleans, `null`, strings.
- **Binary values**: commitments are raw 32-byte strings; encode them unambiguously (length-prefixed, hex or
  base64), never as text that a JSON encoder could reject or re-escape.
- **Text encoding**: UTF-8, and whether strings are normalised (NFC) before serialization.
- **Delimiting**: length prefixes or an escaping scheme, so two different field sets can never produce the same
  bytes.
- **The version string** itself.

Do not rely on `json_encode()` defaults, `serialize()` or `var_export()`: their output depends on flags, on PHP
versions and on the array's insertion order.

## An example implementation

This is an illustration of the properties above, not a recommended format.

```php
use Phil\AuditBundle\Domain\Contract\CanonicalSerializer;

final readonly class LengthPrefixedSerializerV1 implements CanonicalSerializer
{
    public function version(): string
    {
        return 'lp-v1';
    }

    public function serialize(array $fields): string
    {
        ksort($fields, SORT_STRING);
        $out = '';

        foreach ($fields as $name => $value) {
            $encoded = match (true) {
                null === $value => 'n',
                is_bool($value) => $value ? 'b1' : 'b0',
                is_int($value) => 'i'.$value,
                is_string($value) => 's'.bin2hex($value),
                default => throw new InvalidArgumentException(sprintf('Field "%s": floats are not serialized.', $name)),
            };
            $out .= strlen($name).':'.$name.strlen($encoded).':'.$encoded;
        }

        return $out;
    }
}
```

Test it with golden vectors (fixed input, fixed expected bytes) committed alongside it, so a refactoring that
changes one byte fails a test before it reaches a row ([testing.md](testing.md)).
