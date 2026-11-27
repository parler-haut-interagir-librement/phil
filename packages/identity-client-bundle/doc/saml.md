# SAML accounts

Organisations that sign their people in through a SAML 2.0 identity provider, such as Microsoft Entra ID or Okta, reach
the application through
[`phil/saml-sp-bundle`](https://github.com/parler-haut-interagir-librement/saml-sp-bundle). With `saml` turned on,
those sign-ins land in the accounts of the [`UserProvisioner`](provisioning.md): one account model, whether a person
signs in through the OpenID Connect provider or through the identity provider of their organisation.

`phil/saml-sp-bundle` receives the response, checks its signature, its audience, its time window and that it is used
once, and hands the accepted identity to this bundle. Nothing here reads XML.

## Turning it on

```bash
composer require phil/saml-sp-bundle
```

```yaml
# config/packages/phil_identity_client.yaml
phil_identity_client:
    # … the OpenID Connect provider, as before
    claims: ['name', 'email', 'groups']
    saml:
        enabled: true
        verified_email_from: [acme]   # the identity providers trusted to verify addresses
```

```yaml
# config/packages/phil_saml_sp.yaml
phil_saml_sp:
    signing:
        certificate: '%env(file:resolve:SAML_SP_CERTIFICATE)%'
        private_key: '%env(file:resolve:SAML_SP_PRIVATE_KEY)%'
    identity_providers:
        acme:
            metadata_url: 'https://login.example.net/acme/federationmetadata.xml'
            preset: entra_id
            create_accounts: true
```

The bundle names its `phil_identity_client.saml_accounts` service in `phil_saml_sp.accounts`; an `accounts` the
application sets itself still wins. The firewall of the SAML sign-in uses the user provider of this bundle, so that a
later request finds the account through `UserProvisioner::find()`:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        saml:
            pattern: ^/saml
            provider: phil_identity_client
            # … the authenticator of phil/saml-sp-bundle
```

## Accounts created at the first sign-in

At each sign-in, `UserProvisioner::find()` is asked for the subject of the person. When it finds the account,
`provision()` brings it up to date with the claims of this sign-in. When it finds none, the account is created by
`provision()` if the identity provider has `create_accounts: true`, and the person is refused otherwise.

The accounts are not provisioned ahead of the first sign-in, nor removed when a person leaves the organisation: there is
no SCIM. An account no longer used can be found by its last sign-in.

## The identity

| `VerifiedIdentity` | Value |
|---|---|
| `issuer` | The entity ID of the identity provider, as its signed assertion states it |
| `subject` | The key of the identity provider, `\|`, and the stable identifier of the person there: `acme\|5f0c1a52-…` |
| `claims` | The fields of the assertion under their OpenID Connect names, filtered by `claims` |

The subject is unique across identity providers, and it is the identifier `find()` receives on later requests: the
user the provisioner returns has it as its user identifier. The stable identifier is the one the attribute map of the
identity provider reads, such as the object ID of Microsoft Entra ID or the `user_id` of Okta, else the name identifier.

| Claim | From the assertion |
|---|---|
| `email` | The email address, only from an identity provider of `verified_email_from` |
| `email_verified` | Whether the identity provider is in `verified_email_from` |
| `given_name`, `family_name` | The names |
| `name` | The display name |
| `groups`, `roles` | The groups and the roles, as lists |
| `acr` | The authentication context class |
| `auth_time` | The instant of the authentication, in seconds since the Unix epoch |
| `sid` | The session index at the identity provider |

The list `claims` applies as it does for the OpenID Connect provider: a claim it does not name never reaches the
provisioner. An empty field is left out.

## Email addresses

SAML has no statement that an address was verified. An address only reaches the provisioner from an identity provider
the application lists in `verified_email_from`, the way an OpenID Connect `email` only does with `email_verified`.
`VerifiedIdentity::verifiedEmail()` is therefore null for any other identity provider, and an organisation cannot take
over an account by sending the address of someone else. Key the account on the subject, never on the address alone.

## Groups and roles

`groups` and `roles` are what the organisation asserts about its own people. Map them to roles of the application in
the provisioner, or through `group_roles` of `phil/saml-sp-bundle`; never grant a role of the application by its name
as an organisation sends it.
