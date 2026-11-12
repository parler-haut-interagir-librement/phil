<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Application;

use Phil\StructuredData\Id;
use Symfony\Component\HttpFoundation\Request;

/**
 * The page whose graph is being built: the address of the site, the address of the page without its query, its
 * language, and the request when there is one. The stable identifiers of its nodes are made from these addresses.
 */
final readonly class PageContext
{
    /**
     * @param ?string $baseUrl the address of the site, ending with "/"; `null` when neither the configuration nor a request gives it
     * @param ?string $url     the address of the page, without its query nor fragment
     * @param ?string $locale  the language of the page, as a BCP 47 tag
     */
    public function __construct(
        public ?string $baseUrl = null,
        public ?string $url = null,
        public ?string $locale = null,
        public ?Request $request = null,
    ) {
    }

    /**
     * The identifier of a node of the site — `#website`, `#organization` —, or `null` without the site's address.
     */
    public function siteId(string $fragment): ?string
    {
        return null === $this->baseUrl ? null : Id::of($this->baseUrl, $fragment);
    }

    /**
     * The identifier of a node of the page — `#webpage`, `#breadcrumb` —, or `null` without the page's address.
     */
    public function pageId(string $fragment): ?string
    {
        return null === $this->url ? null : Id::of($this->url, $fragment);
    }
}
