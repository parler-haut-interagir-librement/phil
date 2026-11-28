# Security

## The limits

| Limit | Value |
|---|---|
| Components of a bundle | 1 to 50 |
| Units of a component in one bundle | 1 to 1,000 |
| Bundles in one sale | 1 to 1,000 |
| SKU | 64 characters: letters, digits, `_`, `.`, `/`, `-` |
| Participants of a cooperative bundle | 2 to 50 |
| Weight of a participant | 1 to 10,000 |
| Participant or host identifier | 1 to 255 printable characters |
| Bundles found by `containing()` | 1 to 1,000 |

Every loop of the library runs over these bounds, so a quote, a stock count or a stored row read back takes a bounded
time.

## Stored rows

`ProductBundle::fromArray()`, `SoldBundle::fromArray()` and `CooperativeBundle::fromArray()` read rows nobody typed by
hand. Each checks every field's type and bounds and throws `InvalidBundle` on a row out of shape — never a type error,
never another exception — and a row read back writes the same array. The fuzz target `product-bundles-stored` holds
them to it.

A sold line whose shares do not add up to its unit price is refused, so a tampered row cannot refund more than was
paid.

## What is stored

Bundles and sold lines hold SKUs, quantities and amounts, no personal data. A cooperative bundle holds the identifiers
the application gives its participants; give it opaque identifiers, not names or addresses.

## Messages

Exception messages name the bundle, the component or the field at fault, never a price.
