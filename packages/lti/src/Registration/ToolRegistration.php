<?php

declare(strict_types=1);

namespace Phil\Lti\Registration;

use InvalidArgumentException;
use Phil\Url\Url;

/**
 * A tool registered with the platform: the client the platform gave it, where its login initiation is, the addresses
 * the platform may post a launch to, where its keys are published, and the scopes of the services it may ask for.
 */
final readonly class ToolRegistration
{
    /**
     * @param list<Url>    $redirectUrls   the addresses a launch may be posted to, compared whole
     * @param list<string> $scopes         the scopes of the services the tool may be granted
     * @param Url|null     $deepLinkingUrl where a deep linking request is launched, the first redirect address when null
     */
    public function __construct(
        public string $clientId,
        public Url $loginUrl,
        public array $redirectUrls,
        public Url $keySetUrl,
        public array $scopes = [],
        public ?Url $deepLinkingUrl = null,
        public ?string $name = null,
    ) {
        if ('' === $clientId || \strlen($clientId) > 255 || [] === $redirectUrls) {
            throw new InvalidArgumentException('A tool is registered by its client, of 1 to 255 bytes, and at least one redirect address.');
        }
    }

    public function allowsRedirect(Url $url): bool
    {
        return array_any($this->redirectUrls, static fn (Url $redirect): bool => $redirect->equals($url));
    }

    /**
     * Whether an address is on an origin of the tool — the scheme, host and port of its login or of one of its redirect
     * addresses —, as the target of a launch must be.
     */
    public function owns(Url $url): bool
    {
        return array_any([$this->loginUrl, ...$this->redirectUrls], static fn (Url $known): bool => $known->scheme === $url->scheme && $known->host === $url->host && $known->port === $url->port);
    }

    public function grants(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }
}
