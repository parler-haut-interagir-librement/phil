<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Application;

use InvalidArgumentException;

/**
 * What the application registered at Google, and what it asks of a sign-in: its client ID, the Google Workspace domains
 * it accepts — any account, consumer ones included, when there are none —, whether it asks for the person's name, the
 * `prompt` it sends, the leeway of the clocks and how long a sign-in may wait for its answer.
 */
final readonly class GoogleSettings
{
    /** The name of the provider: the path of its routes and the key of its identities in the bundle. */
    public const string NAME = 'google';

    public const string LABEL = 'Google';

    /** The issuer of Google's ID tokens, as its discovery document announces it. */
    public const string ISSUER = 'https://accounts.google.com';

    public const string DISCOVERY_URL = 'https://accounts.google.com/.well-known/openid-configuration';

    /** The values of `prompt` Google accepts; `null` sends none. */
    public const array PROMPTS = ['none', 'consent', 'select_account'];

    private const string DOMAIN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{1,59})$/D';

    /**
     * @param list<string> $hostedDomains the Google Workspace domains accepted, in lowercase; any account when empty
     */
    public function __construct(
        public string $clientId,
        public array $hostedDomains = [],
        public bool $requestProfile = false,
        public ?string $prompt = 'select_account',
        public int $clockSkew = 60,
        public int $attemptLifetime = 600,
    ) {
        if ('' === $clientId || \strlen($clientId) > 255) {
            throw new InvalidArgumentException('The client ID is 1 to 255 bytes.');
        }

        foreach ($hostedDomains as $domain) {
            if (1 !== preg_match(self::DOMAIN, $domain)) {
                throw new InvalidArgumentException(\sprintf('"%s" is not a domain name in lowercase.', $domain));
            }
        }

        if (null !== $prompt && !\in_array($prompt, self::PROMPTS, true)) {
            throw new InvalidArgumentException(\sprintf('The prompt is one of "%s", or none.', implode('", "', self::PROMPTS)));
        }

        if ($clockSkew < 0 || $clockSkew > 300) {
            throw new InvalidArgumentException('The clock skew is 0 to 300 seconds.');
        }

        if ($attemptLifetime < 60 || $attemptLifetime > 3600) {
            throw new InvalidArgumentException('A sign-in waits 60 to 3600 seconds for its answer.');
        }
    }

    /**
     * The scopes asked for: `openid` and `email`, and `profile` only when the name is asked for.
     */
    public function scope(): string
    {
        return $this->requestProfile ? 'openid email profile' : 'openid email';
    }

    /**
     * The `hd` hint of the authorization request: the domain when one is accepted, `*` — any Workspace domain — when
     * several are, none otherwise. It only narrows the account chooser; the `hd` claim is what is checked.
     */
    public function hostedDomainHint(): ?string
    {
        return match (\count($this->hostedDomains)) {
            0 => null,
            1 => $this->hostedDomains[0],
            default => '*',
        };
    }
}
