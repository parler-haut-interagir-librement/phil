<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Unit\Application;

use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\DefaultNodes;
use Phil\StructuredDataBundle\Application\PageContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultNodes::class)]
final class DefaultNodesTest extends TestCase
{
    private const array WEBSITE = ['enabled' => true, 'name' => 'Example Press', 'alternate_name' => 'Example', 'description' => 'Jeux', 'url' => null, 'search_url' => 'https://example.com/recherche?q={search_term_string}'];

    private const array ADDRESS = ['street_address' => null, 'address_locality' => 'Montréal', 'address_region' => null, 'postal_code' => null, 'address_country' => 'CA'];

    private const array ORGANIZATION = ['enabled' => true, 'type' => 'Store', 'name' => 'La Boutique', 'legal_name' => 'Example Press inc.', 'url' => 'https://shop.example', 'logo' => 'https://example.com/logo.png', 'description' => 'Une boutique', 'email' => 'bonjour@example.com', 'telephone' => '+15145550100', 'same_as' => ['https://social.example/example-press'], 'address' => self::ADDRESS];

    public function testTheSiteItsOwnerAndThePage(): void
    {
        $nodes = $this->nodes(new DefaultNodes(self::WEBSITE, self::ORGANIZATION, true), new PageContext('https://example.com/', 'https://example.com/a', 'fr-CA'));

        self::assertSame([
            [
                '@type' => 'Store',
                '@id' => 'https://example.com/#organization',
                'name' => 'La Boutique',
                'legalName' => 'Example Press inc.',
                'url' => 'https://shop.example',
                'logo' => 'https://example.com/logo.png',
                'description' => 'Une boutique',
                'email' => 'bonjour@example.com',
                'telephone' => '+15145550100',
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Montréal', 'addressCountry' => 'CA'],
                'sameAs' => ['https://social.example/example-press'],
            ],
            [
                '@type' => 'WebSite',
                '@id' => 'https://example.com/#website',
                'name' => 'Example Press',
                'alternateName' => 'Example',
                'url' => 'https://example.com/',
                'description' => 'Jeux',
                'inLanguage' => 'fr-CA',
                'publisher' => ['@id' => 'https://example.com/#organization'],
                'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => 'https://example.com/recherche?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
            ],
            ['@type' => 'WebPage', '@id' => 'https://example.com/a#webpage', 'url' => 'https://example.com/a', 'inLanguage' => 'fr-CA', 'isPartOf' => ['@id' => 'https://example.com/#website']],
        ], $nodes);
    }

    public function testWhatIsTurnedOffIsLeftOut(): void
    {
        $page = new PageContext('https://example.com/', 'https://example.com/a');
        $website = [...self::WEBSITE, 'url' => 'https://www.example.com/', 'alternate_name' => null, 'description' => null, 'search_url' => null];
        $organization = [...self::ORGANIZATION, 'type' => 'Organization', 'url' => null, 'legal_name' => null, 'logo' => null, 'description' => null, 'email' => null, 'telephone' => null, 'same_as' => [], 'address' => array_map(static fn (): null => null, self::ADDRESS)];

        self::assertSame([
            ['@type' => 'WebSite', '@id' => 'https://example.com/#website', 'name' => 'Example Press', 'url' => 'https://www.example.com/'],
        ], $this->nodes(new DefaultNodes($website, [...$organization, 'enabled' => false], false), $page));
        self::assertSame([
            ['@type' => 'Organization', '@id' => 'https://example.com/#organization', 'name' => 'La Boutique', 'url' => 'https://example.com/'],
            ['@type' => 'WebPage', '@id' => 'https://example.com/a#webpage', 'url' => 'https://example.com/a'],
        ], $this->nodes(new DefaultNodes([...$website, 'enabled' => false], $organization, true), $page));
        self::assertSame([], $this->nodes(new DefaultNodes(self::WEBSITE, self::ORGANIZATION, true), new PageContext()));
        self::assertCount(2, $this->nodes(new DefaultNodes(self::WEBSITE, self::ORGANIZATION, true), new PageContext('https://example.com/')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nodes(DefaultNodes $defaults, PageContext $page): array
    {
        $nodes = [];

        foreach ($defaults->nodes($page) as $node) {
            self::assertInstanceOf(Node::class, $node);
            $nodes[] = $node->toArray();
        }

        return $nodes;
    }
}
