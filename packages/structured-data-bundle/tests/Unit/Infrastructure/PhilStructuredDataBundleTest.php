<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Unit\Infrastructure;

use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\MenuBundle\Infrastructure\Twig\MenuExtension;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\Schema;
use Phil\StructuredData\Validation\Validator;
use Phil\StructuredDataBundle\Application\Contract\ScriptNonceProvider;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;
use Phil\StructuredDataBundle\Application\DefaultNodes;
use Phil\StructuredDataBundle\Application\PageStructuredData;
use Phil\StructuredDataBundle\Infrastructure\Console\CheckStructuredDataCommand;
use Phil\StructuredDataBundle\Infrastructure\DependencyInjection\StructuredDataServices;
use Phil\StructuredDataBundle\Infrastructure\Http\ContentSecurityPolicyNonce;
use Phil\StructuredDataBundle\Infrastructure\Http\RequestAttributeNonce;
use Phil\StructuredDataBundle\Infrastructure\Menu\MenuBreadcrumbProvider;
use Phil\StructuredDataBundle\Infrastructure\SocialCards\PageShareMetadata;
use Phil\StructuredDataBundle\Infrastructure\Twig\StructuredDataExtension;
use Phil\StructuredDataBundle\PhilStructuredDataBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilStructuredDataBundle::class)]
#[CoversClass(StructuredDataServices::class)]
final class PhilStructuredDataBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_structured_data', $this->extension()->getAlias());
        self::assertSame(['service_container', DefaultNodes::class, PageStructuredData::class, PageShareMetadata::class, StructuredDataExtension::class, Validator::class, CheckStructuredDataCommand::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference(PageStructuredData::class)], $container->getDefinition(PageShareMetadata::class)->getArguments());
        self::assertTrue($container->getDefinition(PageShareMetadata::class)->isAutoconfigured());

        $website = ['enabled' => false, 'name' => null, 'alternate_name' => null, 'description' => null, 'url' => null, 'search_url' => null];
        $address = ['street_address' => null, 'address_locality' => null, 'address_region' => null, 'postal_code' => null, 'address_country' => null];
        $organization = ['enabled' => false, 'type' => 'Organization', 'name' => null, 'legal_name' => null, 'description' => null, 'email' => null, 'telephone' => null, 'url' => null, 'logo' => null, 'same_as' => [], 'address' => $address];
        self::assertEquals([$website, $organization, true], $container->getDefinition(DefaultNodes::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(DefaultNodes::class)->getTag(StructuredDataServices::PROVIDER_TAG));

        $page = $container->getDefinition(PageStructuredData::class);
        self::assertEquals([
            new TaggedIteratorArgument(StructuredDataServices::PROVIDER_TAG),
            new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
            null,
            false,
        ], $page->getArguments());
        self::assertTrue($page->isPublic());
        self::assertSame([['method' => 'reset']], $page->getTag('kernel.reset'));
        self::assertSame(PageStructuredData::class, (string) $container->getAlias(NodeCollector::class));
        self::assertTrue($container->getAlias(NodeCollector::class)->isPublic());

        self::assertSame([['after' => DefaultNodes::class]], $container->getAutoconfiguredInstanceof()[StructuredDataProvider::class]->getTag(StructuredDataServices::PROVIDER_TAG));
        self::assertSame([[]], $container->getAutoconfiguredInstanceof()[ScriptNonceProvider::class]->getTag(StructuredDataServices::NONCE_TAG));

        $twig = $container->getDefinition(StructuredDataExtension::class);
        self::assertEquals([new Reference(PageStructuredData::class), new TaggedIteratorArgument(StructuredDataServices::NONCE_TAG)], $twig->getArguments());
        self::assertTrue($twig->hasTag('twig.attribute_extension'));
        self::assertTrue($twig->hasTag('twig.runtime'));

        $command = $container->getDefinition(CheckStructuredDataCommand::class);
        self::assertEquals([new Reference(Validator::class), new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE), 10.0], $command->getArguments());
        self::assertTrue($command->hasTag('console.command'));
        self::assertSame([null, false], $container->getDefinition(Validator::class)->getArguments());
        self::assertFalse($container->getParameter('phil_structured_data.allow_localhost'));
    }

    public function testLocalhostIsAllowedByTheConfiguration(): void
    {
        $local = $this->load(['allow_localhost' => true, 'base_url' => 'http://localhost:8000', 'website' => ['name' => 'A', 'url' => 'http://shop.localhost/', 'search_url' => 'http://localhost/s?q={search_term_string}'], 'organization' => ['name' => 'A', 'url' => 'http://localhost/', 'logo' => 'http://localhost/logo.png', 'same_as' => ['http://localhost/me']]]);
        self::assertTrue($local->getParameter('phil_structured_data.allow_localhost'));

        try {
            Schema::webSite('Example', 'http://localhost:8000/');
            self::fail('The factories take localhost only once the bundle boots allowing it.');
        } catch (InvalidStructuredData $invalidStructuredData) {
            self::assertStringContainsString('"http://localhost:8000/" is not', $invalidStructuredData->getMessage());
        }

        self::assertSame('https://bücher.example/', $this->load(['base_url' => 'https://bücher.example'])->getDefinition(PageStructuredData::class)->getArgument(3), 'An international host is accepted.');

        $container = $this->load(['allow_localhost' => true]);

        self::assertSame([null, true], $container->getDefinition(Validator::class)->getArguments());
        self::assertTrue($container->getParameter('phil_structured_data.allow_localhost'));
    }

    public function testTheFactoriesTakeLocalhostWhenTheBundleBootsAllowingIt(): void
    {
        foreach ([true, false, null] as $allowed) {
            $container = new ContainerBuilder();

            if (null !== $allowed) {
                $container->setParameter('phil_structured_data.allow_localhost', $allowed);
            }

            $bundle = new PhilStructuredDataBundle();
            $bundle->setContainer($container);
            $bundle->boot();

            try {
                Schema::webSite('Example', 'http://localhost:8000/');
                self::assertTrue($allowed, 'A site on localhost was built.');
            } catch (InvalidStructuredData) {
                self::assertNotTrue($allowed, 'A site on localhost was refused.');
            } finally {
                Schema::allowLocalhost(false);
            }
        }
    }

    public function testLocalhostIsForbiddenAgainWhenTheBuildOfTheNodesFails(): void
    {
        try {
            $this->load(['allow_localhost' => true, 'organization' => ['name' => 'A', 'type' => 'LocalBusiness']]);
            self::fail('The organization needs its address.');
        } catch (InvalidStructuredData) {
            // The configuration is refused, which is the point.
        }

        $this->expectException(InvalidStructuredData::class);
        Schema::webSite('Example', 'http://localhost:8000/');
    }

    public function testAFullConfiguration(): void
    {
        $container = $this->load([
            'base_url' => 'https://example.com',
            'language' => 'fr_ca',
            'request_language' => true,
            'website' => ['name' => 'Example Press', 'alternate_name' => 'Example', 'description' => 'Jeux', 'url' => 'https://www.example.com/', 'search_url' => 'https://example.com/recherche?q={search_term_string}'],
            'organization' => ['type' => 'Store', 'name' => 'La Boutique', 'legal_name' => 'Example Press inc.', 'url' => 'https://shop.example', 'logo' => 'https://example.com/logo.png', 'description' => 'Une boutique', 'email' => 'bonjour@example.com', 'telephone' => '+15145550100', 'same_as' => ['https://social.example/example-press'], 'address' => ['address_locality' => 'Montréal', 'address_country' => 'CA']],
            'web_page' => false,
            'breadcrumb' => ['menu' => 'main'],
            'nonce' => ['request_attribute' => 'script_nonce'],
            'check' => ['timeout' => 3],
        ]);

        [$website, $organization, $webPage] = $container->getDefinition(DefaultNodes::class)->getArguments();
        self::assertSame(['name' => 'Example Press', 'alternate_name' => 'Example', 'description' => 'Jeux', 'url' => 'https://www.example.com/', 'search_url' => 'https://example.com/recherche?q={search_term_string}', 'enabled' => true], $website);
        self::assertIsArray($organization);
        self::assertIsArray($organization['address']);
        self::assertSame(['Store', 'La Boutique', ['https://social.example/example-press'], 'Montréal', 'CA', null], [$organization['type'], $organization['name'], $organization['same_as'], $organization['address']['address_locality'], $organization['address']['address_country'], $organization['address']['postal_code']]);
        self::assertTrue($organization['enabled']);
        self::assertFalse($webPage);

        self::assertSame(['https://example.com/', 'fr-CA', true], \array_slice($container->getDefinition(PageStructuredData::class)->getArguments(), 3));

        $breadcrumb = $container->getDefinition(MenuBreadcrumbProvider::class);
        self::assertEquals([new Reference(MenuExtension::class), 'main', false], $breadcrumb->getArguments());
        self::assertSame([['after' => DefaultNodes::class]], $breadcrumb->getTag(StructuredDataServices::PROVIDER_TAG));

        $nonce = $container->getDefinition(RequestAttributeNonce::class);
        self::assertEquals([new Reference('request_stack'), 'script_nonce'], $nonce->getArguments());
        self::assertSame([[]], $nonce->getTag(StructuredDataServices::NONCE_TAG));

        self::assertSame(3.0, $container->getDefinition(CheckStructuredDataCommand::class)->getArgument(2));
        self::assertSame('HTTPS://example.com/', \array_slice($this->load(['base_url' => 'HTTPS://example.com'])->getDefinition(PageStructuredData::class)->getArguments(), 3)[0]);
        self::assertSame('https://example.com/a/', \array_slice($this->load(['base_url' => 'https://example.com/a///'])->getDefinition(PageStructuredData::class)->getArguments(), 3)[0]);
        self::assertSame(['same_as' => []], array_intersect_key((array) $this->load(['organization' => ['name' => 'A', 'same_as' => []]])->getDefinition(DefaultNodes::class)->getArgument(1), ['same_as' => true]));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'a relative base' => [['base_url' => '/site'], 'The base_url of phil_structured_data is an absolute http:// or https:// address; "/site" is not.'];
        yield 'a base of another scheme' => [['base_url' => 'ftp://example.com'], 'The base_url of phil_structured_data is an absolute http:// or https:// address; "ftp://example.com" is not.'];
        yield 'a base without host' => [['base_url' => 'https:///a'], 'The base_url of phil_structured_data is an absolute http:// or https:// address; "https:///a" is not.'];
        yield 'a base that is no text' => [['base_url' => 3], 'The base_url of phil_structured_data is an absolute http:// or https:// address; "int" is not.'];
        yield 'an address PHP cannot read' => [['base_url' => 'http://:80'], 'The base_url of phil_structured_data is an absolute http:// or https:// address; "http://:80" is not.'];
        yield 'a bad language' => [['language' => 'french'], 'A language is a BCP 47 tag, such as "fr" or "fr-CA"; "french" is not.'];
        yield 'a language that is no text' => [['language' => 3], 'A language is a BCP 47 tag, such as "fr" or "fr-CA"; "" is not.'];
        yield 'a website without a name' => [['website' => ['enabled' => true]], 'The website has a name.'];
        yield 'a relative website' => [['website' => ['name' => 'A', 'url' => '/']], 'The url of phil_structured_data is an absolute http:// or https:// address; "/" is not.'];
        yield 'a search without its term' => [['website' => ['name' => 'A', 'search_url' => 'https://example.com/search?q=']], 'The search_url of phil_structured_data holds {search_term_string} where the words go; "https://example.com/search?q=" does not.'];
        yield 'a relative search' => [['website' => ['name' => 'A', 'search_url' => '/search?q={search_term_string}']], 'The search_url of phil_structured_data is an absolute http:// or https:// address; "/search?q={search_term_string}" is not.'];
        yield 'an organization without a name' => [['organization' => ['enabled' => true]], 'The organization has a name.'];
        yield 'a bad type' => [['organization' => ['name' => 'A', 'type' => 'Local Business']], 'The type of the organization of phil_structured_data: A type is a schema.org term or an IRI; "Local Business" is not.'];
        yield 'a type that is no text' => [['organization' => ['name' => 'A', 'type' => 3]], 'The type of the organization of phil_structured_data: A type is a schema.org term or an IRI; "" is not.'];
        yield 'a relative logo' => [['organization' => ['name' => 'A', 'logo' => 'logo.png']], 'The logo of phil_structured_data is an absolute http:// or https:// address; "logo.png" is not.'];
        yield 'a relative profile' => [['organization' => ['name' => 'A', 'same_as' => ['@example']]], 'The same_as of phil_structured_data is an absolute http:// or https:// address; "@example" is not.'];
        yield 'two sources of nonce' => [['nonce' => ['request_attribute' => 'script_nonce', 'content_security_policy' => true]], 'The nonce of phil_structured_data comes from a request attribute or from the content security policy, not both.'];
        yield 'a local business without its address' => [['organization' => ['name' => 'A', 'type' => 'LocalBusiness']], 'The website or the organization of phil_structured_data: https://example.com/#organization needs address.'];
        yield 'a base with user information' => [['base_url' => 'https://example.com@evil.example/'], 'The base_url of phil_structured_data is an absolute http:// or https:// address; "https://example.com@evil.example/" is not.'];
        yield 'a base after a backslash' => [['base_url' => 'https://\\evil.example/'], 'The base_url of phil_structured_data is an absolute http:// or https:// address; "https://\\evil.example/" is not.'];
        yield 'a base on an IP address' => [['base_url' => 'http://127.0.0.1:8000/'], 'The base_url of phil_structured_data is an absolute http:// or https:// address; "http://127.0.0.1:8000/" is not.'];
        yield 'a base on localhost' => [['base_url' => 'http://localhost:8000/'], 'The base_url of phil_structured_data is on localhost, which allow_localhost leaves false; "http://localhost:8000/" is.'];
        yield 'a website on localhost' => [['website' => ['name' => 'A', 'url' => 'http://shop.localhost/']], 'The url of phil_structured_data is on localhost, which allow_localhost leaves false; "http://shop.localhost/" is.'];
        yield 'a search on localhost' => [['website' => ['name' => 'A', 'search_url' => 'http://localhost/s?q={search_term_string}']], 'The search_url of phil_structured_data is on localhost, which allow_localhost leaves false; "http://localhost/s?q={search_term_string}" is.'];
        yield 'an organization on localhost' => [['organization' => ['name' => 'A', 'url' => 'http://localhost/']], 'The url of phil_structured_data is on localhost, which allow_localhost leaves false; "http://localhost/" is.'];
        yield 'a logo on localhost' => [['organization' => ['name' => 'A', 'logo' => 'http://localhost/logo.png']], 'The logo of phil_structured_data is on localhost, which allow_localhost leaves false; "http://localhost/logo.png" is.'];
        yield 'a profile on localhost' => [['organization' => ['name' => 'A', 'same_as' => ['https://example.com/a', 'http://localhost/me']]], 'The same_as of phil_structured_data is on localhost, which allow_localhost leaves false; "http://localhost/me" is.'];
        yield 'a short timeout' => [['check' => ['timeout' => 0]], 'The value 0 is too small for path "phil_structured_data.check.timeout". Should be greater than or equal to 0.1'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refused')]
    public function testAValueNoGraphCouldHoldIsRefused(array $config, string $message): void
    {
        try {
            $this->load($config);
            self::fail('The configuration is refused.');
        } catch (InvalidConfigurationException|InvalidStructuredData $refused) {
            self::assertStringContainsString($message, $refused->getMessage());
        }
    }

    public function testTheNonceOfTheContentSecurityPolicyIsAskedForOnlyWhenTheSiteWantsIt(): void
    {
        self::assertFalse($this->load([])->hasDefinition(ContentSecurityPolicyNonce::class));
        self::assertFalse($this->load([])->hasDefinition(RequestAttributeNonce::class));

        $nonce = $this->load(['nonce' => ['content_security_policy' => true]])->getDefinition(ContentSecurityPolicyNonce::class);
        self::assertEquals([new Reference(ContentSecurityPolicyContext::class)], $nonce->getArguments());
        self::assertSame([[]], $nonce->getTag(StructuredDataServices::NONCE_TAG));
    }

    public function testTheNodesOfTheConfigurationAreBuiltOnce(): void
    {
        $config = $this->load(['website' => ['name' => 'Example Press'], 'organization' => ['type' => 'LocalBusiness', 'name' => 'A', 'address' => ['address_locality' => 'Montréal']]])->getDefinition(DefaultNodes::class)->getArguments();
        self::assertIsArray($config[1]);
        self::assertSame('LocalBusiness', $config[1]['type']);

        // A configuration that prints no node of its own is built alike.
        self::assertFalse($this->load(['web_page' => false])->getDefinition(DefaultNodes::class)->getArgument(2));
    }

    public function testNullValuesAreLeftNull(): void
    {
        $container = $this->load(['base_url' => null, 'language' => null, 'website' => ['name' => 'A', 'url' => null, 'search_url' => null]]);

        self::assertSame([null, null], \array_slice($container->getDefinition(PageStructuredData::class)->getArguments(), 3, 2));
        $website = $container->getDefinition(DefaultNodes::class)->getArgument(0);
        self::assertIsArray($website);
        self::assertSame([null, null], [$website['url'], $website['search_url']]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilStructuredDataBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
