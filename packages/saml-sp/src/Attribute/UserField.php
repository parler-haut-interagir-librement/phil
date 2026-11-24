<?php

declare(strict_types=1);

namespace Phil\SamlSp\Attribute;

/**
 * What an attribute map reads of an assertion for the user: a stable identifier, the email address, the names, the
 * groups and the roles.
 */
enum UserField: string
{
    /** The stable identifier of the person at the identity provider; the name identifier when it is not mapped. */
    case Identifier = 'identifier';
    case Email = 'email';
    case GivenName = 'given_name';
    case FamilyName = 'family_name';
    case DisplayName = 'display_name';
    case Groups = 'groups';
    case Roles = 'roles';
}
