<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

use Phil\AccessLinks\Exception\InvalidConfiguration;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * The address of a personal link: the page's address and one query parameter carrying the token, `token` by default.
 * Nothing else goes into the link: no recipient, no email address, no campaign.
 *
 * Serve the page with `Referrer-Policy: no-referrer`, so that a link out of it does not carry the token to another
 * site, and make its first visit show the invitation without changing anything: the preview of a mail client opens
 * links too.
 */
final readonly class LinkFormat
{
    public function __construct(
        public Url $page,
        public string $parameter = 'token',
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $parameter)) {
            throw InvalidConfiguration::because('The parameter of the token is 1 to 32 lowercase letters, digits and underscores, starting with a letter.');
        }

        if (null !== $page->fragment) {
            throw InvalidConfiguration::because('The address of the invitation page has no fragment.');
        }
    }

    /**
     * The page's address with the token, in its canonical form.
     */
    public function for(string $token): Url
    {
        $query = (null === $this->page->query ? '' : $this->page->query.'&').$this->parameter.'='.LinkToken::parse($token);

        try {
            return Url::fromString($this->page->scheme.'://'.$this->page->host.(null === $this->page->port ? '' : ':'.$this->page->port).$this->page->path.'?'.$query, allowLocalhost: true);
        } catch (UrlException $urlException) {
            throw InvalidConfiguration::because($urlException->getMessage());
        }
    }
}
