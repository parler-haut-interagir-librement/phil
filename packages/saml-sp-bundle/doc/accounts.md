# Accounts and roles

## Without accounts

When `accounts` names no service, the authenticator signs in a `SamlUser`: its identifier is the key of the identity
provider and the stable identifier of the person there, `acme|5f0c1a52-…`, its roles those of the configuration, with
the email address and the display name of the assertion. It lives in the session; `phil_saml_sp.user_provider`
refreshes it.

## With accounts

An application with accounts of its own implements `SamlAccounts`:

```php
use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSp\Attribute\UserField;
use Phil\SamlSpBundle\Application\Contract\SamlAccounts;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class MemberAccounts implements SamlAccounts
{
    public function __construct(private MemberRepository $members)
    {
    }

    public function find(string $identityProvider, SamlIdentity $identity): ?UserInterface
    {
        return $this->members->findOneBySso($identityProvider, $identity->subject());
    }

    public function create(string $identityProvider, SamlIdentity $identity): UserInterface
    {
        $member = Member::fromSso($identityProvider, $identity->subject(), $identity->value(UserField::Email), $identity->value(UserField::DisplayName));
        $this->members->save($member);

        return $member;
    }
}
```

```yaml
phil_saml_sp:
    accounts: App\Security\MemberAccounts
```

and names its own user provider in the firewall. At each sign-in, `find()` is asked first; when it finds no one and
the identity provider has `create_accounts: true`, `create()` makes the account and `SamlAccountCreated` follows. With
`create_accounts: false`, a person without an account is refused.

Key the account on the identity provider and `subject()` — the object ID of Microsoft Entra ID, the `user_id` of Okta,
the name identifier otherwise —, never on the email address alone: an address changes, and two organisations may
claim the same one. Linking an existing account by its email address is a choice of the application, made only for an
identity provider it trusts to verify addresses.

The accounts are not provisioned ahead of the first sign-in, nor removed when a person leaves the organisation: there is
no SCIM. An account no longer used can be found by its last sign-in, from `SamlLoginSucceeded`.

## Roles

A `SamlUser` gets the `default_roles` of its identity provider, `[ROLE_USER]` by default, then the role `group_roles`
maps each group or role the assertion carries to:

```yaml
            group_roles:
                '0f5b7f6e-1111-4c2d-9e3f-000000000010': ROLE_TEACHER
                Admin: ROLE_ADMIN
```

An application with accounts computes the same list with `SamlUsers::roles($settings, $identity)`, or keeps the roles
in its accounts.
