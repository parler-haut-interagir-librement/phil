<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Application;

use Override;
use Phil\StructuredData\Id;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use Phil\StructuredData\Schema;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;

/**
 * The nodes of the configuration, on every page: the site as a `WebSite`, its owner as an `Organization` or one of its
 * kinds, and the current page as a `WebPage` part of the site. Their identifiers are stable — `#website` and
 * `#organization` at the site's address, `#webpage` at the page's —, so that any package can refer to them or add to
 * them. Each is built by `Schema`, which refuses a node its type would not take: the bundle builds them once when the
 * container is built, so that the configuration is refused there rather than on a page.
 *
 * @phpstan-type WebsiteConfig array{enabled: bool, name: ?string, alternate_name: ?string, description: ?string, url: ?string, search_url: ?string}
 * @phpstan-type AddressConfig array{street_address: ?string, address_locality: ?string, address_region: ?string, postal_code: ?string, address_country: ?string}
 * @phpstan-type OrganizationConfig array{enabled: bool, type: string, name: ?string, legal_name: ?string, url: ?string, logo: ?string, description: ?string, email: ?string, telephone: ?string, same_as: list<string>, address: AddressConfig}
 */
final readonly class DefaultNodes implements StructuredDataProvider
{
    /**
     * @param WebsiteConfig      $website
     * @param OrganizationConfig $organization
     */
    public function __construct(
        private array $website,
        private array $organization,
        private bool $webPage,
    ) {
    }

    #[Override]
    public function nodes(PageContext $page): iterable
    {
        if (null === $page->baseUrl) {
            return [];
        }

        $websiteId = Id::of($page->baseUrl, 'website');
        $organizationId = Id::of($page->baseUrl, 'organization');

        $nodes = [];
        $publisher = $this->organization['enabled'] ? new Reference($organizationId) : null;
        $website = $this->website['enabled'] ? new Reference($websiteId) : null;

        if ($this->organization['enabled']) {
            $nodes[] = $this->organization($organizationId, $page->baseUrl);
        }

        if ($this->website['enabled']) {
            $nodes[] = Schema::webSite(
                (string) $this->website['name'],
                $this->website['url'] ?? $page->baseUrl,
                $websiteId,
                $this->website['alternate_name'],
                $this->website['description'],
                $page->locale,
                $publisher,
                $this->website['search_url'],
            );
        }

        if ($this->webPage && null !== $page->url) {
            $nodes[] = Schema::webPage($page->url, $page->pageId('webpage'), inLanguage: $page->locale, isPartOf: $website);
        }

        return $nodes;
    }

    private function organization(string $id, string $baseUrl): Node
    {
        $address = $this->organization['address'];

        return Schema::organization(
            (string) $this->organization['name'],
            $this->organization['url'] ?? $baseUrl,
            $id,
            $this->organization['logo'],
            $this->organization['description'],
            $this->organization['legal_name'],
            $this->organization['email'],
            $this->organization['telephone'],
            [] === array_filter($address, static fn (?string $value): bool => null !== $value) ? null : Schema::postalAddress($address['street_address'], $address['address_locality'], $address['address_region'], $address['postal_code'], $address['address_country']),
            $this->organization['same_as'],
            $this->organization['type'],
        );
    }
}
