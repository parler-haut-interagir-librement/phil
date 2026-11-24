# Single logout

Single logout is optional: an identity provider without a logout service in its metadata has none, and
`MessageFactory` throws `InvalidMetadata` when asked for a logout message to it.

## Initiated by the service provider

1. The application ends its own session, then writes a `LogoutRequest` with
   `MessageFactory::logoutRequest($sp, $idp, $nameId, $nameIdFormat, $sessionIndex)` — the name identifier and the
   session index of the `SamlIdentity` the person signed in with — and keeps its ID.
2. `MessageSender::send()` puts it on the logout service of the identity provider, HTTP-Redirect preferred, always
   signed.
3. The identity provider answers a `LogoutResponse` to the logout service of the service provider:
   `HttpRedirect::receive($query, $certificates)` or `HttpPost::receive($field)` decodes it, and
   `LogoutProcessor::response($message, $idp, $sp, $requestId)` accepts it, or throws `IdentityProviderRefused` for a
   status other than success — `PartialLogout`, for instance.

## Initiated by the identity provider

1. The identity provider sends a `LogoutRequest` to the logout service of the service provider.
2. `LogoutProcessor::request($message, $idp, $sp)` verifies it and returns a `LogoutRequest`: its ID, the name
   identifier of the person and the session indexes it names.
3. The application ends the sessions of that person, then answers with
   `MessageFactory::logoutResponse($sp, $idp, $request->id)`, success unless it says `false`.

## What is checked

A logout message is signed: on HTTP-Redirect by the signature of the query, which `HttpRedirect::receive()` verifies
with the certificates of the identity provider — the signed text is the query as received, never encoded again —, and on
HTTP-POST by the signature inside, which `LogoutProcessor` verifies as it does a response's. An unsigned logout message
is refused, so that no page can sign a person out by sending their browser to the logout service. Its issuer is the
identity provider, its destination, when present, the logout service of the service provider, its issue instant within
the time accepted, and a `NotOnOrAfter` of a request ahead; the response answers the request this browser sent.
