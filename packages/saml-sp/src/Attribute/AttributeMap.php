<?php

declare(strict_types=1);

namespace Phil\SamlSp\Attribute;

/**
 * Which attributes of an assertion give each field of the user, the first present winning, configured per identity
 * provider. The presets hold the claim names of Microsoft Entra ID and the attribute statements the Okta guide sets;
 * `generic()` the names of the LDAP and eduPerson schemas, by friendly name and by OID.
 */
final readonly class AttributeMap
{
    private const string MS_CLAIMS = 'http://schemas.microsoft.com/identity/claims/';

    private const string MS_WS_2005 = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/';

    private const string MS_WS_2008 = 'http://schemas.microsoft.com/ws/2008/06/identity/claims/';

    /**
     * @param array<value-of<UserField>, list<string>> $names for each field, the attribute names to read, in order
     */
    public function __construct(public array $names = [])
    {
    }

    /**
     * The claims Microsoft Entra ID sends by default: the object identifier, the email address (or the user principal
     * name), the given name, the surname, the display name, and the groups and app roles when they are configured.
     */
    public static function entraId(): self
    {
        return new self([
            UserField::Identifier->value => [self::MS_CLAIMS.'objectidentifier'],
            UserField::Email->value => [self::MS_WS_2005.'emailaddress', self::MS_WS_2005.'name'],
            UserField::GivenName->value => [self::MS_WS_2005.'givenname'],
            UserField::FamilyName->value => [self::MS_WS_2005.'surname'],
            UserField::DisplayName->value => [self::MS_CLAIMS.'displayname'],
            UserField::Groups->value => [self::MS_WS_2008.'groups'],
            UserField::Roles->value => [self::MS_WS_2008.'role'],
        ]);
    }

    /**
     * The attribute statements of the Okta guide: `user_id` from `user.id`, `email`, `firstName`, `lastName`,
     * `displayName`, and a `groups` group attribute statement.
     */
    public static function okta(): self
    {
        return new self([
            UserField::Identifier->value => ['user_id'],
            UserField::Email->value => ['email', 'user.email'],
            UserField::GivenName->value => ['firstName', 'user.firstName'],
            UserField::FamilyName->value => ['lastName', 'user.lastName'],
            UserField::DisplayName->value => ['displayName', 'user.displayName'],
            UserField::Groups->value => ['groups'],
            UserField::Roles->value => ['roles'],
        ]);
    }

    /**
     * The names of the LDAP and eduPerson schemas, by friendly name then by OID.
     */
    public static function generic(): self
    {
        return new self([
            UserField::Identifier->value => ['urn:oid:1.3.6.1.4.1.5923.1.1.1.6', 'eduPersonPrincipalName'],
            UserField::Email->value => ['mail', 'email', 'urn:oid:0.9.2342.19200300.100.1.3'],
            UserField::GivenName->value => ['givenName', 'urn:oid:2.5.4.42'],
            UserField::FamilyName->value => ['sn', 'urn:oid:2.5.4.4'],
            UserField::DisplayName->value => ['displayName', 'urn:oid:2.16.840.1.113730.3.1.241'],
            UserField::Groups->value => ['memberOf', 'groups', 'urn:oid:1.3.6.1.4.1.5923.1.1.1.7'],
            UserField::Roles->value => ['eduPersonAffiliation', 'urn:oid:1.3.6.1.4.1.5923.1.1.1.1'],
        ]);
    }

    /**
     * The same map, with the names of one field replaced; an empty list unmaps it.
     *
     * @param list<string> $names
     */
    public function with(UserField $field, array $names): self
    {
        $map = $this->names;
        $map[$field->value] = $names;

        return new self($map);
    }

    /**
     * The values of each mapped field, from the first of its attributes the assertion carries with a value.
     *
     * @param array<string, list<string>> $attributes the attributes of the assertion, by name
     *
     * @return array<value-of<UserField>, list<string>>
     */
    public function map(array $attributes): array
    {
        $values = [];

        foreach ($this->names as $field => $names) {
            foreach ($names as $name) {
                if ([] !== ($attributes[$name] ?? [])) {
                    $values[$field] = $attributes[$name];

                    break;
                }
            }
        }

        return $values;
    }
}
