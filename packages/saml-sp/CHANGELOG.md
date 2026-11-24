# Changelog

All notable changes to `phil/saml-sp` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-24

### Added

- `SafeXml` and `XmlBudget`: documents from the outside read as UTF-8, without DOCTYPE, entity or processing
  instruction, never reaching the network, within a budget of bytes, depth and nodes (256 KiB, 32 levels, 10,000 nodes
  for a message; 4 MiB, 48 levels, 200,000 nodes for metadata).
- `Certificate`, `PrivateKey` and `KeyPair`: X.509 certificates from PEM or base64 DER, with their expiry and
  fingerprint; RSA private keys hidden from dumps; pairs checked to match.
- `SignatureVerifier`: enveloped XML signatures on one element — one signature, one reference to the element's own
  unique ID, exclusive C14N with its prefix list, the enveloped and exclusive C14N transforms only, RSA with SHA-256 and
  above, SHA-1 only when `SignaturePolicy` allows it, RSA keys of 2048 bits or more, the trusted certificates only,
  never `KeyInfo`; `Signer`: the same signatures written after the `Issuer`.
- `Decrypter`: encrypted assertions with RSA-OAEP (`rsa-oaep-mgf1p`, `xmlenc11#rsa-oaep`) and AES-GCM or AES-CBC,
  the encrypted key inside the data or beside it, every failure reading the same.
- `MessageFactory`: `AuthnRequest` (forced or passive authentication, name identifier format), `LogoutRequest` and
  `LogoutResponse`, with IDs of 160 random bits; `MessageSender`, `HttpRedirect` and `HttpPost`: the HTTP-Redirect
  binding with its signed query, verified on the query as received and inflated within the budget, and the HTTP-POST
  binding signed inside.
- `ResponseProcessor`: the Web Browser SSO profile on HTTP-POST — exactly one assertion, a direct child of the
  response, unique IDs, signatures verified on the elements read, the assertion signed unless configured otherwise,
  status, issuer, destination, recipient, audience, `InResponseTo`, times within a clock skew, encrypted assertions,
  unsolicited responses only when allowed, replay refused — returning a `SamlIdentity`; `SecurityPolicy` with the clock
  skew, the age of a response, the signature policy and the budget.
- `LogoutProcessor`: logout requests and responses of the identity provider, signed in the query or inside, checked
  for issuer, destination, times and the request answered.
- `MetadataReader`: the metadata of an identity provider from an `EntityDescriptor` or an `EntitiesDescriptor`, its
  signature verified when certificates are trusted, HTTPS services read by phil/url, signing certificates for a
  rollover; `MetadataLoader`: from XML or from its address, cached, expired metadata refused;
  `SafeHttpMetadataFetcher` through phil/safe-http; `ServiceProviderMetadata`: the metadata of the service provider.
- `AttributeMap`: the attributes of each `UserField`, with presets for Microsoft Entra ID, Okta and the LDAP and
  eduPerson names; `SamlIdentity`: name identifier, session, attributes and mapped fields, `subject()`.
- `ReplayGuard` and `PendingRequests`: the ports of the accepted assertions and of the requests waiting for their
  response, on a PSR-6 pool (`CacheReplayGuard`, `CachePendingRequests`) or in memory.
- `DbalReplayGuard`: the accepted assertions in a table of a Doctrine DBAL connection (`phil_saml_used_assertion` by
  default), keyed by the SHA-256 of the issuer and the ID, compared byte by byte, the expiry in UTC; the first use is
  one atomic insert on the primary key, refused by the database for a second server, with no read before it;
  `purge()` deletes the expired rows, `configureSchema()` adds the table to the schema of a migration; `doctrine/dbal`
  suggested.
- Response fixtures shaped as Microsoft Entra ID and Okta send them, signed and encrypted with test keys, and the
  signature wrapping, modification, comment, foreign key, unsigned and SHA-1 attacks; the fuzz target `saml-xml`; the
  benchmark `ResponseBench`.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
