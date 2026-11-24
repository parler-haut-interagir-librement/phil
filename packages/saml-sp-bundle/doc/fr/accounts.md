<!-- phil-dev source-sha256=8ad67d4158bae4305c1769a5d206590b5b657b9cbdb6cb18c3b76c8f3564fe09 -->
# Comptes et rôles

## Sans comptes

Quand `accounts` ne nomme aucun service, l'authentificateur connecte un `SamlUser` : son identifiant est la clé du
fournisseur d'identité et l'identifiant stable de la personne là-bas, `acme|5f0c1a52-…`, ses rôles sont ceux de la
configuration, avec l'adresse courriel et le nom d'affichage de l'assertion. Il vit dans la session ;
`phil_saml_sp.user_provider` le rafraîchit.

## Avec des comptes

Une application qui a ses propres comptes implémente `SamlAccounts` :

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

et nomme son propre fournisseur d'utilisateurs dans le pare-feu. À chaque connexion, `find()` est interrogé d'abord ;
quand il ne trouve personne et que le fournisseur d'identité a `create_accounts: true`, `create()` crée le compte et
`SamlAccountCreated` suit. Avec `create_accounts: false`, une personne sans compte est refusée.

Indexez le compte sur le fournisseur d'identité et `subject()` — l'identifiant d'objet de Microsoft Entra ID, le
`user_id` d'Okta, l'identifiant de nom autrement —, jamais sur l'adresse courriel seule : une adresse change, et deux
organisations peuvent réclamer la même. Lier un compte existant par son adresse courriel est un choix de l'application,
fait seulement pour un fournisseur d'identité dont elle sait qu'il vérifie les adresses.

Les comptes ne sont pas provisionnés avant la première connexion, ni retirés quand une personne quitte l'organisation :
il n'y a pas de SCIM. Un compte qui ne sert plus se repère par sa dernière connexion, à partir de `SamlLoginSucceeded`.

## Rôles

Un `SamlUser` reçoit les `default_roles` de son fournisseur d'identité, `[ROLE_USER]` par défaut, puis le rôle auquel
`group_roles` associe chaque groupe ou rôle que l'assertion porte :

```yaml
            group_roles:
                '0f5b7f6e-1111-4c2d-9e3f-000000000010': ROLE_TEACHER
                Admin: ROLE_ADMIN
```

Une application avec des comptes calcule la même liste avec `SamlUsers::roles($settings, $identity)`, ou garde les rôles
dans ses comptes.
