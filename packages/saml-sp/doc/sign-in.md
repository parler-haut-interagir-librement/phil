# Signing in

## The request

`MessageFactory::authnRequest($sp, $idp, forceAuthn: false, isPassive: false)` writes an `AuthnRequest`:

- an ID of 160 random bits, which the response repeats in `InResponseTo`;
- the sign-on service of the identity provider on its `requestBinding` (HTTP-Redirect by default), or on the other
  binding when its metadata has only that one;
- the assertion consumer service of the service provider, on HTTP-POST;
- a `NameIDPolicy` with the format of the service provider when it names one, and `AllowCreate`;
- `ForceAuthn` to ask the person to authenticate again, `IsPassive` to ask the identity provider not to show anything.

The request is signed when the service provider signs its requests (the default) or when the metadata of the identity
provider sets `WantAuthnRequestsSigned`. `MessageSender::send($message, $sp->signing, $relayState)` gives an
`Outbound`: on HTTP-Redirect, the address to redirect to, the request deflated in the query and signed there with
RSA-SHA256; on HTTP-POST, the action and the fields of a form, the request signed inside.

The application keeps the ID of the request until the response comes back, with `PendingRequests`:
`CachePendingRequests` keeps it ten minutes in a PSR-6 pool, and `take()` forgets it as it returns it, so that a
response answers a request once. A `PendingRequest` also holds where the person goes once signed in and, when the
application binds the request to the browser that sent it, the hash of a token that browser holds.

## The response

`ResponseProcessor::process($samlResponse, $idp, $sp, $requestId)` reads the `SAMLResponse` field as posted:

1. the field is base64 within the budget of a message, 256 KiB by default; the document is read by `SafeXml`;
2. the root is a SAML 2.0 `Response`, every `ID` of the document is unique, and the document holds exactly one
   assertion — plain or encrypted —, a direct child of the response;
3. the status is success; anything else throws `IdentityProviderRefused`, which carries the status code and its second
   level, `AuthnFailed` or `RequestDenied` for instance;
4. the issuer of the response, when present, is the identity provider; its destination is the assertion consumer
   service, and a signed response must have one; its `InResponseTo` is the request of this browser; it was issued
   within the last ten minutes, give or take the clock skew;
5. a signature on the response must verify; the encrypted assertion is decrypted; a signature on the assertion must
   verify, and the assertion must be signed unless the identity provider is configured with
   `wantAssertionsSigned: false` and the response is signed;
6. the assertion is SAML 2.0, issued by the identity provider, within the time accepted; its subject has a name
   identifier and a bearer confirmation whose recipient is the assertion consumer service, whose `InResponseTo` is the
   request, and whose `NotOnOrAfter` is ahead; its conditions are valid now and restrict it to an audience that names
   the service provider — every `AudienceRestriction` must; its authentication statement has an instant, and a
   `SessionNotOnOrAfter` ahead when it has one;
7. the assertion was not accepted before: `ReplayGuard::firstUse()` remembers its ID until the latest of its
   expiries plus the clock skew.

What is read — the name identifier, the session, the attributes — is read from the verified elements, by direct
children only.

## The identity

`SamlIdentity` holds the issuer, the name identifier and its format, the `SessionIndex`, the authentication instant,
the end of the session at the identity provider, the authentication context class, the ID of the assertion, every
attribute by name and the fields of the user mapped by the attribute map. `subject()` is the mapped identifier — the
object identifier of Microsoft Entra ID, for instance — or the name identifier: the value to key an account on, since
an email address may change. See [Attributes](attributes.md).

## Unsolicited responses

A response that answers no request, sent by the identity provider when the person opens the application from its
portal, is refused unless the identity provider is configured with `allowUnsolicited: true`; pass `null` as the
request ID. Such a response must not carry an `InResponseTo`, and an assertion answering a request must answer this
browser's: a response captured from another browser is refused either way. Unsolicited responses give up the binding
of a sign-in to the browser that asked for it: allow them only for an identity provider whose portal needs it.

## Encrypted assertions

An `EncryptedAssertion` is decrypted with the key pairs of `ServiceProvider::$encryption`, the current one first and
the previous one during a rollover. The key transport is RSA-OAEP, the data AES in GCM or CBC; see
[Security](security.md#encryption). An identity provider configured with `wantAssertionsEncrypted: true` must encrypt
its assertions; without an encryption key pair, an encrypted assertion is refused.

## Time

The clock is the PSR-20 clock given to the processor. The clock skew of `SecurityPolicy`, two minutes by default and
ten at most, widens every time check both ways; `maxAge`, ten minutes by default, bounds how old a response may be.
