# Microsoft Entra ID

Each organisation registers the application in its own Microsoft Entra ID tenant; the application gets one identity
provider per tenant.

## 1. Configure the identity provider

```yaml
phil_saml_sp:
    identity_providers:
        acme:
            metadata_url: 'https://login.microsoftonline.com/<tenant ID>/federationmetadata/2007-06/federationmetadata.xml?appid=<application ID>'
            preset: entra_id
```

The address is filled in at step 3; until then, any HTTPS address of the tenant keeps the configuration valid.

## 2. Create the enterprise application

In the Microsoft Entra admin center, **Enterprise applications** → **New application** → **Create your own
application**, "Integrate any other application you don't find in the gallery (Non-gallery)". Then **Single sign-on** →
**SAML**, and in **Basic SAML Configuration**:

| Field | Value |
|---|---|
| Identifier (Entity ID) | `https://app.example.org/saml/acme/metadata` |
| Reply URL (Assertion Consumer Service URL) | `https://app.example.org/saml/acme/acs` |
| Sign on URL | `https://app.example.org/saml/acme/login` |
| Logout Url | `https://app.example.org/saml/acme/slo` |

Instead of typing them, **Upload metadata file** takes the file `https://app.example.org/saml/acme/metadata` serves.

## 3. Read its metadata

In **SAML Certificates**, copy the **App Federation Metadata Url** into `metadata_url`. Microsoft signs this metadata:
to require it, put the certificate of the tenant — **Certificate (Base64)**, downloaded from the same section — in
`metadata_certificates`.

## 4. Assign the people

In **Users and groups**, assign the users or groups that may sign in. Microsoft Entra ID refuses the others with
`AADSTS50105` before the application sees them.

## Claims

The default claims match the `entra_id` preset:

| Field | Claim |
|---|---|
| `identifier` | `http://schemas.microsoft.com/identity/claims/objectidentifier` — the object ID, stable for the person |
| `email` | `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress`, then `…/claims/name` (the user principal name) |
| `given_name`, `family_name` | `…/claims/givenname`, `…/claims/surname` |
| `display_name` | `http://schemas.microsoft.com/identity/claims/displayname` |
| `groups` | `http://schemas.microsoft.com/ws/2008/06/identity/claims/groups`, after **Add a group claim** |
| `roles` | `http://schemas.microsoft.com/ws/2008/06/identity/claims/role`, the app roles assigned |

The name identifier is the user principal name by default; the object ID is what accounts are keyed on. Groups come as
object IDs, and are left out past 150 of them — prefer **Groups assigned to the application**, or app roles, and map
them with `group_roles`:

```yaml
            group_roles:
                '0f5b7f6e-1111-4c2d-9e3f-000000000010': ROLE_TEACHER
                Teacher: ROLE_TEACHER          # an app role
```

## Signing and encryption

- **Signing Option**: "Sign SAML assertion", the default, matches `want_assertions_signed: true`; "Sign SAML response
  and assertion" works as well. "Sign SAML response" alone needs `want_assertions_signed: false`.
- **Signing Algorithm**: SHA-256. SHA-1 is refused unless `allow_sha1` is set.
- **Token encryption**: import the certificate of the service provider — the one of `encryption` — and activate it;
  then set `want_assertions_encrypted: true`. Microsoft Entra ID encrypts in AES-256-CBC, which the bundle reads.

## Certificate rollover

Microsoft Entra ID publishes the next signing certificate in the metadata before it uses it: with `metadata_url`, the
bundle reads both, within `metadata_lifetime`. With `metadata_file` or `metadata_xml`, replace the file when the new
certificate appears; `phil:saml:check` reports the expiry of each.

## Troubleshooting

| Refusal (`SamlResponseRefused::$reason`) | Cause |
|---|---|
| The service provider is not an audience of the assertion. | The Identifier (Entity ID) differs from the entity ID of the metadata. |
| The destination of the response is not the assertion consumer service. | The Reply URL differs, or the application is behind a proxy that changes the scheme or the host: configure the trusted proxies of Symfony. |
| The signature does not verify with the certificates of the identity provider. | The metadata is stale, or the certificate was rotated: run `phil:saml:check`. |
| The response does not answer the request of this browser. | The sign-in was started from another browser, took more than `request_lifetime`, or was started from the My Apps portal: see `allow_unsolicited`. |
