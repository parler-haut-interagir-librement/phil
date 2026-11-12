# Strategies

Every strategy implements `Phil\IpAnonymizer\Contract\IpAnonymizer` and returns a string:

```php
interface IpAnonymizer
{
    public function anonymize(IpAddress $address): string;
}
```

## Truncation

`Truncation` clears the host bits past a prefix of each family:

```php
use Phil\IpAnonymizer\Truncation;

$truncation = new Truncation();                       // /24 for IPv4, /48 for IPv6
$truncation->anonymize(IpAddress::parse('203.0.113.45'));            // '203.0.113.0'
$truncation->anonymize(IpAddress::parse('2001:db8:1234:5678::1'));   // '2001:db8:1234::'

new Truncation(16, 32);                               // shorter prefixes hide more
$truncation->transform($address);                     // the truncated IpAddress itself
```

A /24 leaves 256 possible IPv4 hosts. An IPv6 /48 is usually an organisation's whole allocation, a /56 or a /64 a
single home: a /64 truncation of IPv6 is close to no anonymization at all for a residential client.

## Keyed hashing

`KeyedHash` takes the BLAKE2b digest (`sodium_crypto_generichash()`) of the address's bytes, keyed by the current salt
of a `SaltProvider`:

```php
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\ForgottenSalt;

$hash = new KeyedHash(new ForgottenSalt($saltStore, $clock));   // a new random salt each UTC day
$hash->anonymize($address);    // '86400-20400:5f0c9b1e…', 16 bytes of digest by default
$hash->digest($address);       // the IpDigest: saltId and bytes
new KeyedHash($salts, 32);     // 16 to 64 bytes of digest
```

The same address gives the same value for as long as the salt lasts: visits can be counted and clients rate-limited
within the period. Another salt gives unrelated values, and its identifier, at the head of the text, keeps the values
of two periods from ever comparing equal. Without the salt, the digest cannot be reversed by hashing every address:
see [Salts](salts.md).

## Generalisation

### To a network

`NetworkGeneralisation` writes the network that holds the address in CIDR notation, so that the text says how much
was kept:

```php
use Phil\IpAnonymizer\NetworkGeneralisation;

new NetworkGeneralisation()->anonymize(IpAddress::parse('203.0.113.45'));   // '203.0.113.0/24'
new NetworkGeneralisation(16, 32)->network($address);                       // the IpNetwork itself
```

### To a scope

`ScopeGeneralisation` keeps only the `AddressScope` of the address, from the special-purpose address registries of
IANA: `unspecified`, `loopback`, `private`, `shared`, `link-local`, `documentation`, `benchmarking`, `translation`,
`multicast`, `reserved` or `public`.

```php
use Phil\IpAnonymizer\ScopeClassifier;
use Phil\IpAnonymizer\ScopeGeneralisation;

new ScopeGeneralisation()->anonymize(IpAddress::parse('10.1.2.3'));    // 'private'
new ScopeClassifier()->scopeOf($address);                              // AddressScope::Public
```

The unique local `fc00::/7` and the old site-local `fec0::/10` are `private`, like the private IPv4 ranges. IPv6
outside the global unicast `2000::/3` and the listed ranges is `reserved`.

### To a label

`LabelGeneralisation` gives the label of the network that holds the address, from a `NetworkLabeller`, and asks its
fallback strategy otherwise — the scope by default:

```php
use Phil\IpAnonymizer\LabelGeneralisation;
use Phil\IpAnonymizer\NetworkLabels;

$labels = new NetworkLabels([
    '192.0.2.0/24' => 'office',
    '198.51.100.0/22' => 'partner-north',
    '2001:db8::/32' => 'region-east',
]);

new LabelGeneralisation($labels)->anonymize(IpAddress::parse('192.0.2.9'));        // 'office'
new LabelGeneralisation($labels)->anonymize(IpAddress::parse('8.8.8.8'));          // 'public'
new LabelGeneralisation($labels, new Truncation())->anonymize($elsewhere);         // the truncated address
```

`NetworkLabels` takes the label of the longest prefix that holds the address. The table is the application's own —
its offices, its partners, regions it compiled —, so no geolocation database is needed. Each label is 1 to 64 bytes,
each network is in CIDR notation with its host bits clear and listed once. A lookup costs one mask and one hash lookup
per distinct prefix length of the family, at most 33 for IPv4 and 129 for IPv6, whatever the size of the table.

## Composing strategies

`Pipeline` runs `IpTransformer` steps in their order, then a strategy. `Truncation` is both a transformer and a
strategy:

```php
use Phil\IpAnonymizer\Pipeline;

// Rate-limit by IPv4 address and by IPv6 /64, without keeping either.
$limiterKey = new Pipeline(new KeyedHash($salts), new Truncation(32, 64));

// Count visits by /24 and /48 networks, with values that change each day.
$networkVisits = new Pipeline(new KeyedHash($dailySalts), new Truncation());
```

`PerFamily` gives each family its own strategy:

```php
use Phil\IpAnonymizer\PerFamily;

new PerFamily(new Truncation(), new NetworkGeneralisation(24, 56));
```
