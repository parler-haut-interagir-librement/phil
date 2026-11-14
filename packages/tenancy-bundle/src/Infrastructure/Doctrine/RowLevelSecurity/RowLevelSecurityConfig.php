<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The names the row-level security of PostgreSQL is built on: the session variable that holds the active tenant, and
 * the policy each tenant-scoped table carries. Both are written into SQL, so both are checked here: a variable is a
 * dotted pair of lowercase identifiers, as PostgreSQL requires of a custom setting, and a policy one identifier.
 */
final readonly class RowLevelSecurityConfig
{
    public const string DEFAULT_SETTING = 'phil.tenant_id';

    public const string DEFAULT_POLICY = 'phil_tenant_isolation';

    private const string IDENTIFIER = '[a-z_][a-z0-9_]{0,62}';

    public function __construct(
        public string $setting = self::DEFAULT_SETTING,
        public string $policy = self::DEFAULT_POLICY,
    ) {
        if (1 !== preg_match('/^'.self::IDENTIFIER.'\.'.self::IDENTIFIER.'$/D', $setting)) {
            throw InvalidValue::because(\sprintf('The session variable of row-level security is two lowercase identifiers joined by a dot, such as "%s"; "%s" is not.', self::DEFAULT_SETTING, $setting));
        }

        if (1 !== preg_match('/^'.self::IDENTIFIER.'$/D', $policy)) {
            throw InvalidValue::because(\sprintf('The policy of row-level security is a lowercase identifier, such as "%s"; "%s" is not.', self::DEFAULT_POLICY, $policy));
        }
    }
}
