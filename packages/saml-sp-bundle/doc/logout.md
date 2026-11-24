# Single logout

## From the application

Symfony's logout, `/logout` in the firewall, ends the session of the application. When the token came from an identity
provider whose `single_logout` is on, whose metadata has a logout service, and when the service provider has its
signing key, the answer of the logout becomes the signed `LogoutRequest` to the identity provider: the person is signed
out there too. The identity provider answers on `/saml/{idp}/slo`; the response is checked against the request, and the
person goes to `logout_target_path`. A refused response is announced by `SamlResponseRefused`; the session of the
application had already ended.

When the identity provider has no logout service, the logout stays local: the person stays signed in at the identity
provider, and a new sign-in may not ask for their password.

## From the identity provider

When a person signs out of the identity provider, or of another application of the organisation, the identity provider
sends a `LogoutRequest` to `/saml/{idp}/slo`. The bundle verifies its signature, ends the session of the browser that
carries it when that session is the person's, announces `SamlLogoutReceived`, and answers with a signed
`LogoutResponse`.

A logout request carried by the browser reaches only that browser's session, and only when the browser sends its
session cookie: a session cookie `SameSite=Lax`, Symfony's default, follows the redirect of HTTP-Redirect but not the
cross-site `POST` of HTTP-POST, so give the identity provider the logout service on HTTP-Redirect. To end the other
sessions of the person — another device —, listen to `SamlLogoutReceived`: it carries the name identifier and the
session indexes, which the token of each session holds in its attributes `phil_saml_sp.name_id` and
`phil_saml_sp.session_index`.

## Bindings

Logout messages are sent on HTTP-Redirect when the identity provider offers it, on HTTP-POST otherwise, always signed.
Messages received are accepted on both, signed in the query or inside; an unsigned one is refused with 400.
