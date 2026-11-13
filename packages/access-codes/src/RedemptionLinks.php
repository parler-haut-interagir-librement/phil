<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

use Phil\AccessCodes\Exception\InvalidConfiguration;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * The address of the redemption page with a code filled in: the page's address and one query parameter, `code` by
 * default. A person who opens it finds the form ready, and still confirms the redemption.
 *
 * The code is in the query: serve the page with `Referrer-Policy: no-referrer`, so that a link out of it does not
 * carry the code to another site.
 */
final readonly class RedemptionLinks
{
    public function __construct(
        public Url $page,
        public string $parameter = 'code',
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $parameter)) {
            throw InvalidConfiguration::because('The parameter of the code is 1 to 32 lowercase letters, digits and underscores, starting with a letter.');
        }

        if (null !== $page->fragment) {
            throw InvalidConfiguration::because('The address of the redemption page has no fragment.');
        }
    }

    /**
     * The page's address with the code filled in, in its canonical form.
     */
    public function for(string $code): Url
    {
        if (1 !== preg_match('/^[0-9A-Z]{1,65}$/D', $code)) {
            throw InvalidConfiguration::because('A redemption link carries a code in its canonical form.');
        }

        $query = (null === $this->page->query ? '' : $this->page->query.'&').$this->parameter.'='.$code;

        try {
            return Url::fromString($this->page->scheme.'://'.$this->page->host.(null === $this->page->port ? '' : ':'.$this->page->port).$this->page->path.'?'.$query, allowLocalhost: true);
        } catch (UrlException $urlException) {
            throw InvalidConfiguration::because($urlException->getMessage());
        }
    }
}
