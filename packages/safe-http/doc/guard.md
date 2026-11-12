# The guard

## Addresses

`AddressGuard::address($text)` reads an address as phil/url reads it, and refuses with `RefusedAddress`:

- anything but an absolute `http` or `https` address;
- a user name or a password, empty ones included;
- a host that is an IP address, in any of its spellings — `127.0.0.1`, `127.1`, `2130706433`, `0x7f000001`,
  `[::1]` — or a single label, as `localhost`;
- port 0, and an address longer than 2,048 characters once normalised.

An address the guard accepts is normalised: scheme and host in lowercase, an international host in its ASCII form, the
default port dropped.

## Redirects

`AddressGuard::redirect($from, $location)` resolves a `Location` against the address that answered, as a browser
resolves it — absolute, relative to the root or to the path, without a scheme, with `..` — then checks the result as
a first address. `javascript:`, `data:`, `file:` and a redirect to an IP address are refused. A redirect from HTTPS to
HTTP is followed: the guard is about where the request goes, not about how it travels.

## Private networks

A host name is resolved by `NoPrivateNetworkHttpClient`, which refuses an address of the loopback, of a private
network, of the link-local range — the cloud metadata endpoints — or of a reserved range, in IPv4 and in IPv6, and
connects to the address it checked, so that a name cannot resolve elsewhere between the check and the connection. It
checks again the address the transport connected to. A refusal is a `RefusedAddress`. No proxy is used, not even
one named by the environment's `http_proxy` or `https_proxy`: a proxy would resolve the host again, past the address
checked.

## What the guard does not cover

| Concern | Where it belongs |
|---|---|
| Which hosts a site reads at all | The application: an allow list of hosts before the client |
| How often a member makes the site read | A rate limiter before the client, keyed by member or address |
| What the body holds | The reader of the body: an HTML parser bounded in bytes, an XML parser without external entities |
