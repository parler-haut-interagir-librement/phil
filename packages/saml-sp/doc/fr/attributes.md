<!-- phil-dev source-sha256=d5fe7afea3586410c2b6b35375656204d56b5e2a09e1644882f7d92fc23f47c7 -->
# Attributs

Une `AttributeMap` dit, pour chaque `UserField`, quels attributs d'une assertion le fournissent, dans l'ordre : le
premier que l'assertion porte avec une valeur l'emporte. Elle se règle par fournisseur d'identité, puisque chacun nomme
ses revendications à sa façon.

| Champ | Microsoft Entra ID (`entraId()`) | Okta (`okta()`) |
|---|---|---|
| `Identifier` | `http://schemas.microsoft.com/identity/claims/objectidentifier` | `user_id` |
| `Email` | `…/ws/2005/05/identity/claims/emailaddress`, puis `…/claims/name` | `email`, `user.email` |
| `GivenName` | `…/ws/2005/05/identity/claims/givenname` | `firstName`, `user.firstName` |
| `FamilyName` | `…/ws/2005/05/identity/claims/surname` | `lastName`, `user.lastName` |
| `DisplayName` | `http://schemas.microsoft.com/identity/claims/displayname` | `displayName`, `user.displayName` |
| `Groups` | `http://schemas.microsoft.com/ws/2008/06/identity/claims/groups` | `groups` |
| `Roles` | `http://schemas.microsoft.com/ws/2008/06/identity/claims/role` | `roles` |

Les noms `…/ws/2005/05/identity/claims/` commencent par `http://schemas.xmlsoap.org`. Okta n'envoie que les
déclarations d'attributs que son administrateur écrit : le préréglage d'Okta contient les noms que le guide Okta de
phil/saml-sp-bundle définit, dont `user_id` à partir de `user.id`. `generic()` lit les noms LDAP et eduPerson, par nom
convivial puis par OID : `mail`, `givenName`, `sn`, `displayName`, `memberOf`, `eduPersonPrincipalName`,
`eduPersonAffiliation`.

`with(UserField::Email, ['mail'])` remplace les noms d'un champ ; une liste vide le retire de la table.

## L'identité

```php
$identity->subject();                       // the mapped identifier, else the name identifier
$identity->value(UserField::Email);         // the first value, or null
$identity->values(UserField::Groups);       // every value
$identity->attributes['department'] ?? [];  // any attribute, by its name
```

Une valeur est le texte d'un `AttributeValue`, les espaces qui l'entourent retirés ; une valeur marquée `xsi:nil` est
omise, tout comme un attribut sans nom, et les attributs de plusieurs déclarations sont fusionnés. Microsoft Entra ID
envoie les identifiants d'objet des groupes, non leurs noms, et omet les groupes au-delà de 150 ; associez les groupes
aux rôles de l'application par leurs identifiants.
