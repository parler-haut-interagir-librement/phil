# Codes and the counter

## The code

`PickupCodes` issues a code of eight characters from Crockford's base 32 — `0123456789ABCDEFGHJKMNPQRSTVWXYZ`, without
I, L, O nor U —, each drawn by `random_int()`: forty bits. `IssuedCode::display()` shows it in two groups, `7KQ2-M9XD`.

Reading a code forgives what people type: case, spaces and dashes are ignored, `O` reads as `0`, `I` and `L` as `1`.
`PickupCodes::normalize()` gives the eight characters, or `null`.

Only the digest is kept: `hash_hmac('sha256', order."\n".code, key)`, 64 hexadecimal digits, where `key` is
`hash_hkdf('sha256', secret, 32, 'phil_local_pickup.code_digest')`. It is bound to the order, so a code is worth
nothing for another order, and keyed by a key derived from the secret, so a copy of the database alone cannot be tried
offline, and no other use of the same secret yields the same digests. `PickupCodes::matches()` compares digests with `hash_equals()`.

## The QR code

`IssuedCode::payload()` is the order number, a colon and the code: `WEB-1042:7KQ2M9XD`. `qrCode()` encodes it with
phil/qr-code at the error correction asked (medium by default) and `svg()` draws it with a title for screen readers.
A scanner's text is read back with `ScannedCode::parse()`, or straight into a request with `HandOverRequest::scanned()`;
a payload longer than 97 bytes, without a colon, or without a code after the last colon is refused with
`InvalidPickup`.

## The counter

```php
$counter = new Counter($pickupRepository, new PickupCodes($secret), $handOverLog, maxAttempts: 5);

$handOver = $counter->handOver(new HandOverRequest(
    order: 'WEB-1042',
    code: '7kq2 m9xd',
    station: 'counter-1',
    staff: 'staff-3',
    at: new DateTimeImmutable(),
    collector: 'Élise Tremblay',
));
```

| Situation | Result |
|---|---|
| No pickup for the order | `UnknownPickup` |
| The pickup is not ready | `PickupRefused`, reason `state_<state>` |
| The code took `maxAttempts` wrong attempts | `CodeLocked` |
| The pickup is ready but its notice not sent | `PickupRefused`, reason `no_code` |
| A wrong code | The attempt is counted and saved; `CodeRefused` with `attemptsLeft`, or `CodeLocked` on the last one |
| The right code | The pickup is picked up, its code spent, the `HandOver` written to the journal and returned |

The attempt limit is 1 to 20, five by default. A locked code stays locked until `Pickups::resend()` sends a new one,
which starts the count again.

## Someone collecting in the customer's place

The customer may designate a person when placing the order; the notice shows the name. At the counter, the staff checks
who is there and passes their name as `collector`. The `HandOver` then keeps the collector's name and the designated
one side by side, and `byThirdParty()` is `true`, so that a later check reads what the staff saw against what the
customer asked. Without a `collector`, the customer collected and no name is kept.

## Registers

`Contract\PickupDesk` is the port a counter screen and a point-of-sale register both call; `Counter` implements it. A
convention booth's register hands an order over with the register's identifier as `station` and the cashier as
`staff`. The register's side — scanning the code in the sale screen and calling the port — belongs to the point of sale;
this library has no dependency on it.
