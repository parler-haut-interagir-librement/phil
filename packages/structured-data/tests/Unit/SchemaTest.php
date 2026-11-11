<?php

declare(strict_types=1);

namespace Phil\StructuredData\Tests\Unit;

use Closure;
use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use Phil\StructuredData\Schema;
use Phil\StructuredData\Type\Article;
use Phil\StructuredData\Type\BreadcrumbList;
use Phil\StructuredData\Type\Event;
use Phil\StructuredData\Type\EventAttendanceMode;
use Phil\StructuredData\Type\EventStatus;
use Phil\StructuredData\Type\ImageObject;
use Phil\StructuredData\Type\ItemAvailability;
use Phil\StructuredData\Type\Offer;
use Phil\StructuredData\Type\Organization;
use Phil\StructuredData\Type\Person;
use Phil\StructuredData\Type\Place;
use Phil\StructuredData\Type\PostalAddress;
use Phil\StructuredData\Type\Product;
use Phil\StructuredData\Type\WebPage;
use Phil\StructuredData\Type\WebSite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schema::class)]
final class SchemaTest extends TestCase
{
    private const string BASE = 'https://example.com/';

    /**
     * @return iterable<string, array{Closure(): Node, Closure(): Node}>
     */
    public static function factories(): iterable
    {
        $organization = new Reference(self::BASE.'#organization');
        $offer = Offer::of('19.99', 'CAD', availability: ItemAvailability::InStock, url: self::BASE.'shirt', priceValidUntil: '2027-12-31', seller: $organization, properties: ['sku' => 'A1']);

        yield 'Article' => [
            static fn (): Node => Schema::article('Spring tournament', self::BASE.'#article', $organization, '2027-04-01', '2027-04-02', self::BASE.'a.png', $organization, self::BASE.'news', 'The results.', 'fr-CA', $organization, 'BlogPosting', ['wordCount' => 300]),
            static fn (): Node => Article::of('Spring tournament', self::BASE.'#article', $organization, '2027-04-01', '2027-04-02', self::BASE.'a.png', $organization, self::BASE.'news', 'The results.', 'fr-CA', $organization, 'BlogPosting', ['wordCount' => 300]),
        ];
        yield 'Product' => [
            static fn (): Node => Schema::product('Shirt', self::BASE.'#shirt', 'Cotton.', self::BASE.'s.png', 'NAQ001', '0123456789012', 'Le Gnome', $offer, self::BASE.'shirt', ['color' => 'Red']),
            static fn (): Node => Product::of('Shirt', self::BASE.'#shirt', 'Cotton.', self::BASE.'s.png', 'NAQ001', '0123456789012', 'Le Gnome', $offer, self::BASE.'shirt', ['color' => 'Red']),
        ];
        yield 'Offer' => [
            static fn (): Node => Schema::offer('19.99', 'CAD', self::BASE.'#offer', ItemAvailability::InStock, self::BASE.'shirt', '2027-12-31', $organization, ['sku' => 'A1']),
            static fn (): Node => Offer::of('19.99', 'CAD', self::BASE.'#offer', ItemAvailability::InStock, self::BASE.'shirt', '2027-12-31', $organization, ['sku' => 'A1']),
        ];
        yield 'Event' => [
            static fn (): Node => Schema::event('Tournament', '2027-04-10T10:00:00-04:00', 'Salle Saint-Denis', self::BASE.'#event', '2027-04-10T18:00:00-04:00', 'Cards.', self::BASE.'event', self::BASE.'e.png', EventStatus::Scheduled, EventAttendanceMode::Offline, $offer, $organization, $organization, 'fr', 'SocialEvent', ['maximumAttendeeCapacity' => 40]),
            static fn (): Node => Event::of('Tournament', '2027-04-10T10:00:00-04:00', 'Salle Saint-Denis', self::BASE.'#event', '2027-04-10T18:00:00-04:00', 'Cards.', self::BASE.'event', self::BASE.'e.png', EventStatus::Scheduled, EventAttendanceMode::Offline, $offer, $organization, $organization, 'fr', 'SocialEvent', ['maximumAttendeeCapacity' => 40]),
        ];
        yield 'Place' => [
            static fn (): Node => Schema::place('Salle', '1 rue A, Montréal', self::BASE.'#place', self::BASE.'salle', 45.5, -73.6, '+15145550123', ['maximumAttendeeCapacity' => 80]),
            static fn (): Node => Place::of('Salle', '1 rue A, Montréal', self::BASE.'#place', self::BASE.'salle', 45.5, -73.6, '+15145550123', ['maximumAttendeeCapacity' => 80]),
        ];
        yield 'PostalAddress' => [
            static fn (): Node => Schema::postalAddress('1 rue A', 'Montréal', 'QC', 'H2X 1Y4', 'CA', '12', self::BASE.'#address', ['name' => 'Office']),
            static fn (): Node => PostalAddress::of('1 rue A', 'Montréal', 'QC', 'H2X 1Y4', 'CA', '12', self::BASE.'#address', ['name' => 'Office']),
        ];
        yield 'Organization' => [
            static fn (): Node => Schema::organization('Le Gnome', self::BASE, self::BASE.'#organization', self::BASE.'logo.png', 'Games.', 'Le Gnome inc.', 'a@example.com', '+15145550123', new Reference(self::BASE.'#address'), ['https://example.org/gnome'], 'Corporation', ['foundingDate' => '2020-01-01']),
            static fn (): Node => Organization::of('Le Gnome', self::BASE, self::BASE.'#organization', self::BASE.'logo.png', 'Games.', 'Le Gnome inc.', 'a@example.com', '+15145550123', new Reference(self::BASE.'#address'), ['https://example.org/gnome'], 'Corporation', ['foundingDate' => '2020-01-01']),
        ];
        yield 'Person' => [
            static fn (): Node => Schema::person('Alice', self::BASE.'alice', self::BASE.'#alice', self::BASE.'alice.png', 'Author', $organization, 'alice@example.com', ['https://example.org/alice'], ['birthDate' => '1990-01-01']),
            static fn (): Node => Person::of('Alice', self::BASE.'alice', self::BASE.'#alice', self::BASE.'alice.png', 'Author', $organization, 'alice@example.com', ['https://example.org/alice'], ['birthDate' => '1990-01-01']),
        ];
        yield 'WebSite' => [
            static fn (): Node => Schema::webSite('Le Gnome', self::BASE, self::BASE.'#website', 'Gnome', 'Games.', 'fr-CA', $organization, self::BASE.'search?q={search_term_string}', ['copyrightYear' => 2027]),
            static fn (): Node => WebSite::of('Le Gnome', self::BASE, self::BASE.'#website', 'Gnome', 'Games.', 'fr-CA', $organization, self::BASE.'search?q={search_term_string}', ['copyrightYear' => 2027]),
        ];
        yield 'WebPage' => [
            static fn (): Node => Schema::webPage(self::BASE.'about', self::BASE.'about#page', 'About', 'Who we are.', 'fr-CA', new Reference(self::BASE.'#website'), new Reference(self::BASE.'#breadcrumb'), self::BASE.'a.png', '2027-01-01', '2027-02-01', 'AboutPage', ['keywords' => 'games']),
            static fn (): Node => WebPage::of(self::BASE.'about', self::BASE.'about#page', 'About', 'Who we are.', 'fr-CA', new Reference(self::BASE.'#website'), new Reference(self::BASE.'#breadcrumb'), self::BASE.'a.png', '2027-01-01', '2027-02-01', 'AboutPage', ['keywords' => 'games']),
        ];
        yield 'BreadcrumbList' => [
            static fn (): Node => Schema::breadcrumbList([['name' => 'Home', 'url' => self::BASE], ['name' => 'About']], self::BASE.'#breadcrumb'),
            static fn (): Node => BreadcrumbList::of([['name' => 'Home', 'url' => self::BASE], ['name' => 'About']], self::BASE.'#breadcrumb'),
        ];
        yield 'ImageObject' => [
            static fn (): Node => Schema::imageObject(self::BASE.'a.png', self::BASE.'#image', 'A die.', 800, 600, 'https://creativecommons.org/licenses/by/4.0/', $organization, 'Le Gnome', self::BASE.'a', ['encodingFormat' => 'image/png']),
            static fn (): Node => ImageObject::of(self::BASE.'a.png', self::BASE.'#image', 'A die.', 800, 600, 'https://creativecommons.org/licenses/by/4.0/', $organization, 'Le Gnome', self::BASE.'a', ['encodingFormat' => 'image/png']),
        ];
    }

    /**
     * @param Closure(): Node $schema
     * @param Closure(): Node $factory
     */
    #[DataProvider('factories')]
    public function testEachFactoryGivesTheNodeOfItsType(Closure $schema, Closure $factory): void
    {
        self::assertEquals($factory(), $schema());
    }

    public function testTheWarningsAreLeftToTheValidator(): void
    {
        $article = Schema::article('Only a headline', author: new Reference(self::BASE.'#nobody'));

        self::assertSame(['@type' => 'Article', 'headline' => 'Only a headline', 'author' => ['@id' => self::BASE.'#nobody']], $article->toArray());
        self::assertSame(['@type' => 'PostalAddress'], Schema::postalAddress()->toArray());
    }

    /**
     * @return iterable<string, array{Closure(): Node, string}>
     */
    public static function refusals(): iterable
    {
        yield 'a product without offer, review or rating' => [static fn (): Node => Schema::product('Shirt'), 'Product @graph[0] needs one of offers, review, aggregateRating.'];
        yield 'an offer in a bad currency' => [static fn (): Node => Schema::offer('19.99', 'cad'), 'Offer @graph[0] › priceCurrency is an ISO 4217 code of three capitals; "cad" is not.'];
        yield 'a product whose offer is in a bad currency' => [static fn (): Node => Schema::product('Shirt', offers: Offer::of('19.99', 'cad')), 'Product @graph[0] › offers › priceCurrency is an ISO 4217 code of three capitals; "cad" is not.'];
        yield 'an event on no date' => [static fn (): Node => Schema::event('Tournament', 'next Saturday', 'Salle'), 'Event @graph[0] › startDate is an ISO 8601 date or date and time; "next Saturday" is not.'];
        yield 'a place with neither name nor address' => [static fn (): Node => Schema::place(), 'Place @graph[0] needs one of name, address.'];
        yield 'an organization with a relative logo' => [static fn (): Node => Schema::organization('Le Gnome', logo: '/logo.png'), 'Organization @graph[0] › logo is an absolute HTTP or HTTPS address; "/logo.png" is not.'];
        yield 'a person with a relative page' => [static fn (): Node => Schema::person('Alice', 'alice'), 'Person @graph[0] › url is an absolute HTTP or HTTPS address; "alice" is not.'];
        yield 'a site in no language' => [static fn (): Node => Schema::webSite('Le Gnome', self::BASE, inLanguage: 'french!'), 'WebSite @graph[0] › inLanguage is a BCP 47 language tag; "french!" is not.'];
        yield 'a page at a relative address' => [static fn (): Node => Schema::webPage('/about'), 'WebPage @graph[0] › url is an absolute HTTP or HTTPS address; "/about" is not.'];
        yield 'an empty breadcrumb' => [static fn (): Node => Schema::breadcrumbList([]), 'BreadcrumbList @graph[0] needs itemListElement.'];
        yield 'an image at a relative address' => [static fn (): Node => Schema::imageObject('a.png'), 'ImageObject @graph[0] › contentUrl'];
        yield 'an article of a bad date' => [static fn (): Node => Schema::article('News', datePublished: '2027-02-30'), 'Article @graph[0] › datePublished is an ISO 8601 date or date and time; "2027-02-30" is not.'];
        yield 'two errors' => [static fn (): Node => Schema::offer('19.99', 'cad', url: 'shirt'), 'Offer @graph[0] › priceCurrency is an ISO 4217 code of three capitals; "cad" is not. Offer @graph[0] › url is an absolute HTTP or HTTPS address; "shirt" is not.'];
    }

    /**
     * @param Closure(): Node $build
     */
    #[DataProvider('refusals')]
    public function testANodeWithAnErrorIsRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidStructuredData::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }
}
