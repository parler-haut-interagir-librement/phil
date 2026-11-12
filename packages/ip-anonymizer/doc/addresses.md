# Addresses and networks

## `IpAddress`

An address is held as its bytes in network order — 4 for IPv4, 16 for IPv6 — with its `IpFamily`.

**Anonymize the client's address, not the proxy's.** Behind a load balancer or a CDN, `REMOTE_ADDR` is the proxy's
address; declare the proxies under `framework.trusted_proxies` and `trusted_headers`, then pass
`Request::getClientIp()`. Without that setting, every visitor shares the digest of the proxy, and with a forwarded
header trusted from anyone, a visitor chooses its own address.

```php
use Phil\IpAnonymizer\IpAddress;

$address = IpAddress::parse('2001:DB8::0001');     // throws InvalidIpAddress on anything else
$address = IpAddress::tryParse($untrusted);        // null on anything else
$address = IpAddress::fromBytes($packed);          // 4 or 16 bytes, as inet_pton() returns them

$address->family;      // IpFamily::V6
$address->bytes;       // "\x20\x01\x0d\xb8…"
$address->toString();  // '2001:db8::1'
```

### What the parser accepts

The text is checked before `inet_pton()` reads it, so that every platform accepts the same addresses:

| Text | Read as |
|---|---|
| `192.0.2.1` | IPv4 |
| `2001:db8::1`, `2001:0DB8:0:0::1` | IPv6, written `2001:db8::1` |
| `::ffff:192.0.2.1`, `::ffff:c000:201` | the IPv4 address `192.0.2.1` |
| `::192.0.2.1` (IPv4-compatible, deprecated) | the IPv6 address `::c000:201` |

And refuses:

| Text | Why |
|---|---|
| More than 45 characters | The longest address is `ffff:ffff:ffff:ffff:ffff:ffff:255.255.255.255`. |
| `fe80::1%eth0` | A zone index names an interface of the host, and some platforms fold it into the bytes. |
| `[::1]`, a space or a line break around the address | Brackets, spaces and line breaks belong to the text around an address. |
| `010.0.0.1` | A leading zero in a dotted part is octal to some readers and decimal to others. |
| `192.0.2`, `0x1.0.0.1`, `localhost` | Not the four dotted decimal parts of an address. |

**An IPv4-mapped address is its IPv4 address.** A dual-stack socket reports an IPv4 client as `::ffff:192.0.2.1`;
reading it as `192.0.2.1` gives the client the same truncation, digest and label whichever socket accepted it.

### The text of an address

`toString()` writes the canonical text of RFC 5952 itself rather than through `inet_ntop()`, whose output differs
between platforms: dotted decimal for IPv4; for IPv6, lowercase groups without leading zeros, the longest run of two or
more zero groups — the first of equal runs — written `::`, and never a dotted part.

## `IpNetwork`

A network is an address whose host bits are clear and a prefix length.

```php
use Phil\IpAnonymizer\IpNetwork;

IpNetwork::containing($address, 48);          // the /48 that holds the address
IpNetwork::parse('192.0.2.0/24');             // CIDR notation
IpNetwork::parse('192.0.2.1/24');             // throws InvalidNetwork: host bits set

$network->contains(IpAddress::parse('192.0.2.77'));   // true
$network->toString();                                  // '192.0.2.0/24'
```

The prefix length is 0 to 32 for IPv4 and 0 to 128 for IPv6, written in decimal without a leading zero. An address
of the other family is never contained.
