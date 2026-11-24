# Okta

Each organisation creates an application in its own Okta org; the application gets one identity provider per org.

## 1. Create the application

In the Okta Admin Console, **Applications** → **Create App Integration** → **SAML 2.0**. In **Configure SAML**:

| Field | Value |
|---|---|
| Single sign-on URL | `https://app.example.org/saml/acme/acs`, "Use this for Recipient URL and Destination URL" checked |
| Audience URI (SP Entity ID) | `https://app.example.org/saml/acme/metadata` |
| Name ID format | Persistent, or EmailAddress |
| Application username | Okta username |
| Response | Signed |
| Assertion Signature | Signed |
| Signature Algorithm | RSA-SHA256 |

## Attribute statements

The `okta` preset reads these statements; add them under **Attribute Statements**:

| Name | Value |
|---|---|
| `user_id` | `user.id` — the stable identifier accounts are keyed on |
| `email` | `user.email` |
| `firstName` | `user.firstName` |
| `lastName` | `user.lastName` |
| `displayName` | `user.displayName` |

and under **Group Attribute Statements**, `groups` with a filter, "Matches regex" `.*` or a narrower one. The values
are group names; map them with `group_roles`.

Without `user_id`, the name identifier is the identifier: with the Persistent format it is stable; with EmailAddress,
an address that changes gives the person a new account.

## 2. Configure the identity provider

In the **Sign On** tab, copy the **Metadata URL**:

```yaml
phil_saml_sp:
    identity_providers:
        acme:
            metadata_url: 'https://example.okta.com/app/<application ID>/sso/saml/metadata'
            preset: okta
            binding: redirect
```

## 3. Assign the people

In **Assignments**, assign the people or groups that may sign in.

## Single logout

Under **Advanced Settings**, check **Enable Single Logout**, set the **Single Logout URL** to
`https://app.example.org/saml/acme/slo`, the **SP Issuer** to the entity ID, and upload the **Signature Certificate**:
the certificate of `signing`. Okta then publishes its logout service in the metadata, and the bundle carries the logout
both ways.

## Encryption

Under **Advanced Settings**, set **Assertion Encryption** to Encrypted, choose AES256-GCM, RSA-OAEP, and upload the
certificate of `encryption`; then set `want_assertions_encrypted: true`.

## IdP-initiated sign-in

A person who opens the application from the Okta dashboard arrives with an unsolicited response, refused by default.
To allow it, set `allow_unsolicited: true` and, in Okta, a **Default RelayState** that is a local path such as
`/courses`; see [Security](security.md#unsolicited-responses).
