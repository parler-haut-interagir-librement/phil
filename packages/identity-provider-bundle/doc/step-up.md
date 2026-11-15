# Step-up

Sensitive actions — changing the address or the password, adding or removing a passkey, turning the authenticator
application on or off, new recovery codes, removing a phone number, closing every other session, unlinking a federated
identity — need a recent proof of the account's strongest method. The bundle builds this on the sudo mode of Symfony
Security 8.2.

## The proofs

Symfony Security 8.2 records on the token each method proven and when (`AuthenticationProofsListener`). The bundle
states its methods with `AuthenticationMethodBadge`, with the values of RFC 8176:

| Sign-in | Proofs |
|---|---|
| A password, by any authenticator of the firewall | `pwd` |
| A passkey | `hwk`, `user`, and `mfa` when the person was verified |
| A code of the authenticator application, a recovery code | `otp` |
| A sign-in link, a federated provider | none stated: `*` (unspecified) |

A second sign-in in the same session adds its proofs to those the token holds.

## PHIL_STEP_UP

`StepUpVoter` grants `PHIL_STEP_UP` when the account proved, within `step_up.lifetime` seconds:

- `hwk`, `otp` or `mfa`, for an account with a passkey or an authenticator application;
- one of them or `pwd`, for an account with a password and no stronger method;
- any of them or `*`, for an account that signs in only through a federated provider, which proves it by signing in
  there again.

Denied, the vote calls `requestReAuthentication('PHIL_STEP_UP')`. Symfony Security then keeps where the person was
going and starts the firewall's `re_authentication_entry_point`, the bundle's `ReAuthenticationEntryPoint`, which sends
them to `/reauthenticate`: the methods the account has, strongest first — a passkey, a code, the password through the
sign-in form of `login_route`, the federated providers it is linked to. The new proof brings them back to the action.

An application protects its own actions the same way:

```php
#[IsGranted('PHIL_STEP_UP')]
public function deleteProject(): Response
```

`step_up.attribute` set to `IS_AUTHENTICATED_RECENTLY` makes the bundle's actions use Symfony's own attribute, decided
by the firewall's `recent_authentication_lifetime`, whatever the method was.
