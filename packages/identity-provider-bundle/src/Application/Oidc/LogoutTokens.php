<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Oidc;

use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Psr\Clock\ClockInterface;
use stdClass;

/**
 * The logout tokens of OpenID Connect Back-Channel Logout 1.0, section 2.4: a JSON Web Token of type `logout+jwt`,
 * signed by the active key, whose `iss` is the provider, `aud` the client, `iat` now, `exp` LIFETIME seconds later,
 * `jti` new, `sub` the account as the client knows it and `sid` the session, with the event `http://schemas.openid.net/event/backchannel-logout`
 * and no `nonce`.
 */
final readonly class LogoutTokens
{
    public const string EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    public const int LIFETIME = 120;

    public function __construct(
        private SigningKeys $keys,
        private ProviderSettings $settings,
        private ClockInterface $clock,
    ) {
    }

    public function issue(Client $client, string $accountId, string $sessionId): string
    {
        $now = $this->clock->now()->getTimestamp();

        return Jws::encode([
            'iss' => $this->settings->issuer,
            'sub' => new Subjects($this->settings)->of($accountId, $client),
            'aud' => $client->id(),
            'iat' => $now,
            'exp' => $now + self::LIFETIME,
            'jti' => Base64Url::encode(random_bytes(24)),
            'sid' => $sessionId,
            'events' => [self::EVENT => new stdClass()],
        ], $this->keys->active(), ['typ' => 'logout+jwt']);
    }
}
