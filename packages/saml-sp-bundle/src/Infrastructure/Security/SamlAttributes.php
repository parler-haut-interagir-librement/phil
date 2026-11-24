<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\Security;

/**
 * The attributes of the security token of a person signed in through an identity provider: what single logout needs
 * once the request is gone, and where the person goes once signed in.
 */
final class SamlAttributes
{
    public const string IDENTITY_PROVIDER = 'phil_saml_sp.identity_provider';

    public const string NAME_ID = 'phil_saml_sp.name_id';

    public const string NAME_ID_FORMAT = 'phil_saml_sp.name_id_format';

    public const string SESSION_INDEX = 'phil_saml_sp.session_index';

    public const string TARGET = 'phil_saml_sp.target';

    public const array ALL = [self::IDENTITY_PROVIDER, self::NAME_ID, self::NAME_ID_FORMAT, self::SESSION_INDEX, self::TARGET];

    private function __construct()
    {
    }
}
