# Reference

## `Phil\SamlSp\Provider`

| Type | Role |
|---|---|
| `ServiceProvider` | The application: entity ID, assertion consumer and logout services, key pairs, name identifier format. |
| `IdentityProvider` | An identity provider as trusted: metadata, attribute map, what its responses must be. |
| `SecurityPolicy` | Clock skew, age of a response, signature policy, budget of a message. |

## `Phil\SamlSp\Message`

| Type | Role |
|---|---|
| `MessageFactory` | Writes the `AuthnRequest`, the `LogoutRequest` and the `LogoutResponse`; `newId()`. |
| `OutgoingMessage` | A message to send: ID, document, destination, binding, parameter, whether it is signed. |
| `ReceivedMessage` | A message decoded from a binding, and whether its binding verified its signature. |
| `ResponseProcessor` | Reads, verifies and checks a response; returns a `SamlIdentity`. |
| `LogoutProcessor` | Reads a logout request or response of the identity provider. |
| `LogoutRequest` | A verified logout request: ID, issuer, name identifier, session indexes. |
| `PendingRequest` | A request waiting for its response; `toArray()`, `fromArray()`. |
| `TimeWindow` | Internal: the instant of a check widened by the clock skew. |

## `Phil\SamlSp\Binding`

| Type | Role |
|---|---|
| `Binding` | `Redirect` or `Post`, with the URI of the binding. |
| `HttpRedirect` | The message deflated in a signed query; `url()`, `receive()`. |
| `HttpPost` | The message in a form field; `form()`, `receive()`, `decode()`. |
| `MessageSender` | Puts an `OutgoingMessage` on its binding, signed when it must be. |
| `Outbound` | What the browser carries: an address to redirect to, or a form. |

## `Phil\SamlSp\Attribute`

| Type | Role |
|---|---|
| `AttributeMap` | The attributes of each field; `entraId()`, `okta()`, `generic()`, `with()`, `map()`. |
| `UserField` | `Identifier`, `Email`, `GivenName`, `FamilyName`, `DisplayName`, `Groups`, `Roles`. |
| `SamlIdentity` | The person an assertion signs in; `subject()`, `value()`, `values()`. |

## `Phil\SamlSp\Metadata`

| Type | Role |
|---|---|
| `IdentityProviderMetadata` | Entity ID, services by binding, signing certificates, `validUntil`; `singleSignOn()`, `singleLogout()`. |
| `MetadataReader` | Reads the metadata of an identity provider, its signature verified when certificates are trusted. |
| `MetadataLoader` | Loads metadata from XML or from its address, cached, expired metadata refused. |
| `ServiceProviderMetadata` | Writes the metadata of the service provider. |

## `Phil\SamlSp\Signature`, `Phil\SamlSp\Encryption`, `Phil\SamlSp\Key`

| Type | Role |
|---|---|
| `SignatureVerifier` | Verifies an enveloped signature on one element. |
| `Signer` | Signs an element, the signature after its `Issuer`. |
| `SignaturePolicy` | SHA-1 allowed or not, the smallest RSA key. |
| `SignatureAlgorithm`, `DigestAlgorithm` | The RSA signatures and the digests read and written. |
| `Decrypter` | Decrypts an `EncryptedAssertion`. |
| `DataEncryption` | The AES ciphers of an encrypted assertion. |
| `Certificate` | An X.509 certificate; `fromPem()`, `fromBase64()`, `notAfter()`, `fingerprint()`. |
| `PrivateKey` | An RSA private key, hidden from dumps. |
| `KeyPair` | A private key and its certificate, checked to match. |

## `Phil\SamlSp\Contract`, `Phil\SamlSp\Store`, `Phil\SamlSp\Http`

| Type | Role |
|---|---|
| `Contract\ReplayGuard` | Remembers accepted assertions until they expire. |
| `Contract\PendingRequests` | Keeps the requests waiting for their response. |
| `Contract\MetadataFetcher` | Fetches metadata from its address. |
| `Store\DbalReplayGuard` | The replay guard on a table of a Doctrine DBAL connection, atomic; `purge()`, `configureSchema()`, `key()`. |
| `Store\CacheReplayGuard`, `Store\InMemoryReplayGuard` | Replay guards on a PSR-6 pool and in memory. |
| `Store\CachePendingRequests`, `Store\InMemoryPendingRequests` | Pending requests on a PSR-6 pool and in memory. |
| `Http\SafeHttpMetadataFetcher` | Fetches metadata through phil/safe-http. |

## `Phil\SamlSp\Xml`

| Type | Role |
|---|---|
| `SafeXml` | Reads a document from the outside, refused before parsing what SAML never needs. |
| `XmlBudget` | The bytes, depth and nodes of a document; `message()`, `metadata()`. |
| `Ns` | The namespaces and identifiers of SAML, XML Signature and XML Encryption. |
| `Elements`, `IdMap`, `Instant`, `Base64`, `XmlWriter` | Internal: direct children, IDs, instants, base64 in constant time, writing. |

## `Phil\SamlSp\Exception`

| Type | Role |
|---|---|
| `SamlException` | The interface of every exception of the library. |
| `InvalidXml` | A document refused before it is read. |
| `InvalidSignature` | A signature missing, malformed, refused or that does not verify. |
| `InvalidMessage` | A message refused by a check of the profile. |
| `ReplayedAssertion` | An assertion already accepted. |
| `IdentityProviderRefused` | A status other than success; `status`, `subStatus`. |
| `InvalidMetadata`, `MetadataUnavailable` | Metadata refused, or out of reach. |
| `InvalidKey` | A certificate or key that cannot be read, does not match, or is too short. |
| `DecryptionFailed` | An encrypted assertion that cannot be decrypted. |
