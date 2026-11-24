# Security

## XML

`SafeXml` reads every document from the outside — a response, a logout message, metadata, a decrypted assertion:

- the text is UTF-8, without a NUL byte, and an XML declaration names no other encoding, so that the checks that
  follow read what the parser reads;
- a DOCTYPE or an entity declaration is refused before parsing: no external entity is resolved, no entity expanded;
  the network is never reached (`LIBXML_NONET`);
- a processing instruction or an entity reference is refused after parsing;
- the bytes, the depth of the elements and the number of nodes are bounded by an `XmlBudget`: 256 KiB, 32 levels and
  10,000 nodes for a message, 4 MiB, 48 levels and 200,000 nodes for metadata;
- a message on HTTP-Redirect is inflated within the bytes of the budget, so that a small query cannot expand into a
  large document.

A comment inside a value hides nothing: the text of an element is all its text nodes, the comment left out, which is
also what the canonical form the signature covers holds.

## Signatures

`SignatureVerifier` verifies an enveloped signature on one element and accepts nothing looser:

- exactly one `ds:Signature` child, with one `SignedInfo`, one `SignatureValue` and exactly one `Reference`;
- the `URI` of the reference is `#` and the `ID` of that element, and that `ID` resolves, in the whole document, to
  that very element;
- canonicalisation by exclusive C14N without comments, with its `InclusiveNamespaces` prefix list; the transforms are
  the enveloped signature and exclusive C14N, once each — no XPath, XSLT or other transform runs;
- RSA with SHA-256, SHA-384 or SHA-512, digests of the same family; SHA-1 refused unless `SignaturePolicy::$allowSha1`;
- the trusted certificates of the identity provider verify the signature, never a key or a certificate the
  signature carries in its `KeyInfo`; a certificate whose RSA key is shorter than 2048 bits is refused;
- the digest is compared in constant time.

The document is left as it was found: the signature taken out to compute the digest is put back in its place.

## Signature wrapping

Signature wrapping moves the signed element elsewhere in the document and plants a forged one where the reader looks.
`ResponseProcessor` gives it nothing to work with:

- every `ID` of the document is unique, so that a reference cannot resolve to a copy;
- the document holds exactly one assertion, plain or encrypted, and it is a direct child of the response: an assertion
  hidden in an extension, in `Advice`, in a signature's `Object` or anywhere else is refused, even unsigned;
- the signature verified is the one the assertion, or the response, holds as its own child, referencing itself;
- what is read is read from the verified element by its direct children, never by a search of the document.

The tests replay each of these attacks from fixtures; see [Testing](testing.md).

## The checks of the profile

Status, issuer (with the `entity` format, when one is named), destination, recipient, audience, the request answered
by the response and by the confirmation, `NotBefore`, `NotOnOrAfter`, the issue instants, the end of the session: see
[Signing in](sign-in.md#the-response). The addresses are compared in the normalised form of phil/url, so that a
difference of case in the host or a default port does not refuse a response, and nothing else passes.

## Replay

`ReplayGuard::firstUse()` remembers the ID of each accepted assertion, under its issuer, until the latest of its
expiries plus the clock skew: one presented again is refused with `ReplayedAssertion`. `DbalReplayGuard` keeps it in a
database table whose primary key makes the first use one atomic insert, on any number of servers; `CacheReplayGuard`
keeps it in a PSR-6 pool shared by every worker and every server, where a pool cleared on deploy forgets the assertions
still valid, and a pool local to each server lets an assertion be accepted again by another server. See
[The replay store](replay-store.md).
`PendingRequests::take()` forgets a request as it returns it, so that a response answers a request once.

## Encryption

`Decrypter` reads the key transports RSA-OAEP with MGF1 SHA-1 (`rsa-oaep-mgf1p`) and `xmlenc11#rsa-oaep` with the same
digest for OAEP and MGF1 — never RSA PKCS#1 v1.5, open to Bleichenbacher's attack — and the data in AES-128, 192 or
256, in GCM or CBC. CBC, which Microsoft Entra ID and Okta send by default, is open to padding oracle attacks when the
answer tells a bad padding from another failure: every decryption failure past the algorithms reads the same, the
assertion inside must be signed (a signature on the response alone covers the encrypted data, which is enough to stop a
modified cipher text), and GCM is the one to choose where the identity provider offers it.

## Keys

`PrivateKey` keeps its PEM and passphrase out of `var_dump()` and stack traces. `KeyPair` checks that the key is the
certificate's. The library signs only with RSA-SHA256 and above, never SHA-1.

## Messages and logs

The message of every `SamlException` names what was refused — never a name identifier, an attribute value or a key —
so that it can be logged.

## What is left to the application

- Keeping the private keys out of the repository and out of the web root; rotating them, the next certificate published
  in the metadata before it signs.
- A replay guard shared by every server — the database table of `DbalReplayGuard`, or a shared cache pool —, and a
  shared cache pool for the pending requests.
- Binding a request to the browser that sent it, which the bundle does with a cookie.
- Deciding what an account at first sign-in may see: the attributes come from the identity provider of an organisation,
  and a group claim is only as good as its configuration there.
