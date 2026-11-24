# Metadata

## Reading the metadata of an identity provider

`MetadataReader::read($xml, $entityId = null, $trusted = [])` reads an `EntityDescriptor`, or the one of an
`EntitiesDescriptor` its entity ID names, into `IdentityProviderMetadata`:

- the entity ID;
- the sign-on and logout services on HTTP-Redirect and HTTP-POST, the first of each binding, other bindings left out;
  each address is read by phil/url and must be HTTPS;
- the signing certificates: those of the `KeyDescriptor`s for signing and for any use, without repeats, in order —
  several during a certificate rollover;
- `WantAuthnRequestsSigned`, and `validUntil` when it is there.

When certificates are trusted, the root of the document must carry a signature that verifies with one of them, as
Microsoft Entra ID signs its federation metadata. The document is read by `SafeXml` within the metadata budget: 4 MiB,
48 levels, 200,000 nodes. `allowLocalhost: true` accepts `localhost` and plain HTTP services, for an identity provider
run on the developer's machine.

Every refusal is an `InvalidMetadata`.

## Loading it from its address

`MetadataLoader` reads metadata from XML, `fromXml()`, or from its address, `fromUrl()`, and refuses metadata past its
`validUntil`. The text fetched is kept in a cache of the Symfony cache contracts for a lifetime, a day by default
(60 seconds to 30 days), so that a sign-in never waits on the identity provider; metadata the reader refuses is
removed from the cache at once. `fromUrl(..., refresh: true)` fetches it again.

`SafeHttpMetadataFetcher` fetches through phil/safe-http: HTTPS only, private networks refused, redirects checked one
by one and never away from HTTPS, 5 seconds of silence and 15 in all, 4 MiB at most. Every failure is a
`MetadataUnavailable`.

## The metadata of the service provider

`ServiceProviderMetadata::xml($sp, $wantAssertionsSigned = true, $validUntil = null)` writes the `EntityDescriptor` an
identity provider imports:

- `AuthnRequestsSigned` and `WantAssertionsSigned`;
- the signing certificate, and each encryption certificate with the encryption methods the service provider reads:
  AES-256-GCM, AES-128-GCM, AES-256-CBC, AES-128-CBC;
- the logout service on HTTP-Redirect and HTTP-POST, when the service provider has one;
- the name identifier format, when the service provider names one;
- the assertion consumer service on HTTP-POST.
