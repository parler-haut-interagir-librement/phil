<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

/**
 * The modules the configuration turned on. A module off has no route, no screen and no service: a site that does
 * not want sign-in links or SMS codes turns them off, and they cannot be reached.
 */
final readonly class Modules
{
    public const string ACCOUNTS = 'accounts';

    public const string PASSWORD = 'password';

    public const string PASSKEYS = 'passkeys';

    public const string TOTP = 'totp';

    public const string RECOVERY_CODES = 'recovery_codes';

    public const string LOGIN_LINK = 'login_link';

    public const string SMS = 'sms';

    public const string SESSIONS = 'sessions';

    public const string STEP_UP = 'step_up';

    public const string EXTERNAL = 'external';

    public const string OIDC = 'oidc';

    public const array ALL = [self::ACCOUNTS, self::PASSWORD, self::PASSKEYS, self::TOTP, self::RECOVERY_CODES, self::LOGIN_LINK, self::SMS, self::SESSIONS, self::STEP_UP, self::EXTERNAL, self::OIDC];

    /**
     * @param list<string> $enabled
     */
    public function __construct(
        private array $enabled,
    ) {
    }

    public function has(string $module): bool
    {
        return \in_array($module, $this->enabled, true);
    }

    /**
     * @return list<string>
     */
    public function enabled(): array
    {
        return array_values(array_intersect(self::ALL, $this->enabled));
    }
}
