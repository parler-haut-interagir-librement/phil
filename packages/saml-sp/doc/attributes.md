# Attributes

An `AttributeMap` says, for each `UserField`, which attributes of an assertion give it, in order: the first one the
assertion carries with a value wins. It is set per identity provider, since each names its claims its own way.

| Field | Microsoft Entra ID (`entraId()`) | Okta (`okta()`) |
|---|---|---|
| `Identifier` | `http://schemas.microsoft.com/identity/claims/objectidentifier` | `user_id` |
| `Email` | `…/ws/2005/05/identity/claims/emailaddress`, then `…/claims/name` | `email`, `user.email` |
| `GivenName` | `…/ws/2005/05/identity/claims/givenname` | `firstName`, `user.firstName` |
| `FamilyName` | `…/ws/2005/05/identity/claims/surname` | `lastName`, `user.lastName` |
| `DisplayName` | `http://schemas.microsoft.com/identity/claims/displayname` | `displayName`, `user.displayName` |
| `Groups` | `http://schemas.microsoft.com/ws/2008/06/identity/claims/groups` | `groups` |
| `Roles` | `http://schemas.microsoft.com/ws/2008/06/identity/claims/role` | `roles` |

The `…/ws/2005/05/identity/claims/` names start with `http://schemas.xmlsoap.org`. Okta sends only the attribute
statements its administrator writes: the Okta preset holds the names the Okta guide of phil/saml-sp-bundle sets,
`user_id` from `user.id` among them. `generic()` reads the LDAP and eduPerson names, by friendly name then by OID:
`mail`, `givenName`, `sn`, `displayName`, `memberOf`, `eduPersonPrincipalName`, `eduPersonAffiliation`.

`with(UserField::Email, ['mail'])` replaces the names of one field; an empty list unmaps it.

## The identity

```php
$identity->subject();                       // the mapped identifier, else the name identifier
$identity->value(UserField::Email);         // the first value, or null
$identity->values(UserField::Groups);       // every value
$identity->attributes['department'] ?? [];  // any attribute, by its name
```

A value is the text of an `AttributeValue`, spaces around it trimmed; a value marked `xsi:nil` is left out, an
attribute without a name too, and the attributes of several statements are merged. Microsoft Entra ID sends group
object identifiers, not names, and leaves the groups out past 150 of them; map groups to roles of the application by
their identifiers.
