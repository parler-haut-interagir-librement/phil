# Salted commitments and erasure

**State: `Salt` and `Commitment` are implemented and tested.** The erasure operation over audit rows is not
implemented; this page describes the contract the two value objects give it.

## Why commitments

An audit trail must be tamper-evident, and personal information must be erasable. Hashing the plaintext into
the chain makes the two incompatible: deleting the value breaks the hash. Instead, every personal field enters the
chain only through a **per-row, per-field salted commitment**. Erasure deletes the plaintext **and the salt** and
keeps the commitment, so the row hash does not move and every anchor still verifies.

## The construction

```text
commitment = HMAC-SHA256(key = salt, message = value)      // raw 32 bytes
```

- **HMAC, not `H(salt ‖ value)`.** Hashed concatenation is home-made and open to length extension; HMAC is the
  standard construction nobody has to defend.
- **The salt is the HMAC key**, at least 128 bits, drawn from a CSPRNG, specific to one row and one field. It is
  what makes the commitment irreversible once deleted: a short or predictable salt turns a commitment on an e-mail
  address into a dictionary attack.
- **Binary output**, exactly 32 bytes.

The construction is a one-way door of the same kind as the canonical serialization: it closes at the first row
that carries a commitment. Changing the algorithm, the key/message roles or the output encoding after that makes
every existing commitment unverifiable.

## `Salt`

`Phil\AuditBundle\Domain\Commitment\Salt` — `final readonly`, public `$bytes`.

| Member | Behaviour |
|---|---|
| `MIN_BYTES = 16` | 128 bits |
| `static generate(): self` | `random_bytes(16)`: exactly the minimum, from the CSPRNG |
| `static fromBytes(string $bytes): self` | Reads back a stored salt; refuses fewer than 16 bytes with `InvalidValue` (`A salt is at least 16 bytes; this one is 15.`). Longer salts are accepted. |

## `Commitment`

`Phil\AuditBundle\Domain\Commitment\Commitment` — `final readonly`, public `$bytes`.

| Member | Behaviour |
|---|---|
| `static of(Salt $salt, string $value): self` | `hash_hmac('sha256', $value, $salt->bytes, true)` |
| `static fromBytes(string $bytes): self` | Reads back a stored commitment; refuses anything but 32 bytes with `InvalidValue` (`A commitment is 32 bytes; this one is 31.`) — a truncated column or another construction would otherwise make `matches()` fail without saying why |
| `matches(Salt $salt, string $value): bool` | Recomputes and compares in constant time (`hash_equals`) |
| `equals(Commitment $other): bool` | Constant-time comparison of two commitments |

The constant-time comparison matters: a comparison that stops at the first differing byte lets an attacker
measure, byte by byte, how close a guessed value is.

The construction is pinned by a published vector in the tests: RFC 4231 test case 1 (a 20-byte key of `0x0b`,
message `Hi There`).

## Usage

Writing a row:

```php
use Phil\AuditBundle\Domain\Commitment\Commitment;
use Phil\AuditBundle\Domain\Commitment\Salt;

$salt = Salt::generate();
$commitment = Commitment::of($salt, $email);

// Store, for this row and this field:
//   plaintext  -> $email                (erasable)
//   salt       -> $salt->bytes          (erasable, 16 bytes)
//   commitment -> $commitment->bytes    (kept forever, 32 bytes, part of the hashed fields)
```

Verifying that a stored plaintext was not altered:

```php
$stored = Commitment::fromBytes($row['email_commitment']);
$ok = $stored->matches(Salt::fromBytes($row['email_salt']), $row['email']);
```

Store salts and commitments in binary columns (`bytea`, `BINARY`/`VARBINARY`), or encode them yourself; the value
objects hold raw bytes.

## Erasure contract

To erase a personal field of a row:

1. delete the plaintext;
2. delete the salt;
3. keep the commitment untouched;
4. render the field as `[erased]` when reading the row.

After step 2 the commitment can no longer be checked against any candidate value, so it cannot be undone by a
dictionary, and the row hash — computed over the commitment — is unchanged. A free-text field written by a
person (a reason, a comment) cannot be split into personal and non-personal parts and is redacted whole.

Which fields are personal, and when to erase, are the application's decisions.

## Errors

Both value objects throw `Phil\DddBundle\Domain\Exception\InvalidValue` (an `InvalidArgumentException` that
implements `Phil\DddBundle\Domain\Exception\DomainError`).
