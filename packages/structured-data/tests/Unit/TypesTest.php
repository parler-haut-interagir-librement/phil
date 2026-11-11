<?php

declare(strict_types=1);

namespace Phil\StructuredData\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\StructuredData\Graph;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use Phil\StructuredData\Type\Article;
use Phil\StructuredData\Type\Book;
use Phil\StructuredData\Type\BookFormat;
use Phil\StructuredData\Type\BreadcrumbList;
use Phil\StructuredData\Type\CreativeWork;
use Phil\StructuredData\Type\Event;
use Phil\StructuredData\Type\EventAttendanceMode;
use Phil\StructuredData\Type\EventStatus;
use Phil\StructuredData\Type\FAQPage;
use Phil\StructuredData\Type\ImageObject;
use Phil\StructuredData\Type\ItemAvailability;
use Phil\StructuredData\Type\LocalBusiness;
use Phil\StructuredData\Type\Offer;
use Phil\StructuredData\Type\Organization;
use Phil\StructuredData\Type\Person;
use Phil\StructuredData\Type\Place;
use Phil\StructuredData\Type\PostalAddress;
use Phil\StructuredData\Type\Product;
use Phil\StructuredData\Type\Properties;
use Phil\StructuredData\Type\SoftwareApplication;
use Phil\StructuredData\Type\SoftwareSourceCode;
use Phil\StructuredData\Type\Thing;
use Phil\StructuredData\Type\WebPage;
use Phil\StructuredData\Type\WebSite;
use Phil\StructuredData\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Thing::class)]
#[CoversClass(WebSite::class)]
#[CoversClass(WebPage::class)]
#[CoversClass(BreadcrumbList::class)]
#[CoversClass(Organization::class)]
#[CoversClass(LocalBusiness::class)]
#[CoversClass(Person::class)]
#[CoversClass(Event::class)]
#[CoversClass(Place::class)]
#[CoversClass(PostalAddress::class)]
#[CoversClass(Product::class)]
#[CoversClass(Offer::class)]
#[CoversClass(Book::class)]
#[CoversClass(CreativeWork::class)]
#[CoversClass(Article::class)]
#[CoversClass(SoftwareSourceCode::class)]
#[CoversClass(SoftwareApplication::class)]
#[CoversClass(FAQPage::class)]
#[CoversClass(ImageObject::class)]
#[CoversClass(Properties::class)]
#[CoversClass(EventStatus::class)]
#[CoversClass(EventAttendanceMode::class)]
#[CoversClass(ItemAvailability::class)]
#[CoversClass(BookFormat::class)]
final class TypesTest extends TestCase
{
    private const string BASE = 'https://example.com/';

    public function testAThingOfAnyType(): void
    {
        self::assertSame([
            '@type' => 'Recipe',
            '@id' => self::BASE.'soupe#recipe',
            'recipeYield' => '4',
            'name' => 'Soupe',
            'url' => self::BASE.'soupe',
            'description' => 'Chaude',
            'image' => self::BASE.'soupe.jpg',
            'sameAs' => ['https://wiki.example/soupe'],
        ], Thing::of('Recipe', 'Soupe', self::BASE.'soupe', 'Chaude', self::BASE.'soupe.jpg', ['https://wiki.example/soupe'], self::BASE.'soupe#recipe', ['recipeYield' => '4', 'name' => 'ignored'])->toArray());
        self::assertSame(['@type' => 'Thing', 'sameAs' => ['https://a.example']], Thing::of('Thing', properties: ['sameAs' => ['https://a.example']])->toArray());
    }

    public function testASiteWithItsSearch(): void
    {
        self::assertSame([
            '@type' => 'WebSite',
            '@id' => self::BASE.'#website',
            'name' => 'Le Gnome',
            'alternateName' => 'Gnome',
            'url' => self::BASE,
            'description' => 'Jeux',
            'inLanguage' => 'fr-CA',
            'publisher' => ['@id' => self::BASE.'#org'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => self::BASE.'search?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ], WebSite::of('Le Gnome', self::BASE, self::BASE.'#website', 'Gnome', 'Jeux', 'fr-CA', Reference::to(self::BASE.'#org'), self::BASE.'search?q='.WebSite::SEARCH_TERM)->toArray());
        self::assertSame(['@type' => 'WebSite', 'copyrightYear' => 2026, 'name' => 'A', 'url' => self::BASE], WebSite::of('A', self::BASE, properties: ['copyrightYear' => 2026])->toArray());
    }

    public function testAPageOfTheSite(): void
    {
        self::assertSame([
            '@type' => 'ContactPage',
            '@id' => self::BASE.'contact#webpage',
            'url' => self::BASE.'contact',
            'name' => 'Contact',
            'description' => 'Nous joindre',
            'inLanguage' => 'fr',
            'isPartOf' => ['@id' => self::BASE.'#website'],
            'breadcrumb' => ['@id' => self::BASE.'contact#breadcrumb'],
            'primaryImageOfPage' => self::BASE.'contact.jpg',
            'datePublished' => '2026-01-01T00:00:00+00:00',
            'dateModified' => '2026-02-01',
        ], WebPage::of(self::BASE.'contact', self::BASE.'contact#webpage', 'Contact', 'Nous joindre', 'fr', Reference::to(self::BASE.'#website'), Reference::to(self::BASE.'contact#breadcrumb'), self::BASE.'contact.jpg', new DateTimeImmutable('2026-01-01 00:00 UTC'), '2026-02-01', 'ContactPage')->toArray());
        self::assertSame(['@type' => 'WebPage', 'url' => self::BASE], WebPage::of(self::BASE)->toArray());
    }

    public function testABreadcrumb(): void
    {
        self::assertSame([
            '@type' => 'BreadcrumbList',
            '@id' => self::BASE.'a/b#breadcrumb',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => self::BASE],
                ['@type' => 'ListItem', 'position' => 2, 'name' => ['@value' => 'Articles', '@language' => 'fr'], 'item' => self::BASE.'a'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'B'],
            ],
        ], BreadcrumbList::of([
            ['name' => 'Accueil', 'url' => self::BASE],
            ['name' => new LanguageString('Articles', 'fr'), 'url' => self::BASE.'a'],
            ['name' => 'B', 'url' => null],
        ], self::BASE.'a/b#breadcrumb')->toArray());
        self::assertSame(['@type' => 'BreadcrumbList'], BreadcrumbList::of([])->toArray());
    }

    public function testAnOrganisationAndALocalBusiness(): void
    {
        $address = PostalAddress::of('1 rue Principale', 'Montréal', 'QC', 'H2X 1Y4', 'CA', 'BP 12', self::BASE.'#address', ['name' => 'Siège']);

        self::assertSame([
            '@type' => 'PostalAddress',
            '@id' => self::BASE.'#address',
            'name' => 'Siège',
            'streetAddress' => '1 rue Principale',
            'postOfficeBoxNumber' => 'BP 12',
            'addressLocality' => 'Montréal',
            'addressRegion' => 'QC',
            'postalCode' => 'H2X 1Y4',
            'addressCountry' => 'CA',
        ], $address->toArray());
        self::assertSame(['@type' => 'PostalAddress'], PostalAddress::of()->toArray());

        self::assertSame([
            '@type' => 'NGO',
            '@id' => self::BASE.'#org',
            'name' => 'Le Gnome',
            'legalName' => 'Le Gnome Archiviste inc.',
            'url' => self::BASE,
            'logo' => self::BASE.'logo.png',
            'description' => 'Jeux',
            'email' => 'bonjour@example.com',
            'telephone' => '+15145550100',
            'address' => ['@id' => self::BASE.'#address'],
            'sameAs' => ['https://social.example/gnome'],
        ], Organization::of('Le Gnome', self::BASE, self::BASE.'#org', self::BASE.'logo.png', 'Jeux', 'Le Gnome Archiviste inc.', 'bonjour@example.com', '+15145550100', $address->reference(), ['https://social.example/gnome'], 'NGO')->toArray());
        self::assertSame(['@type' => 'Organization', 'name' => 'A'], Organization::of('A')->toArray());

        self::assertSame([
            '@type' => 'Store',
            '@id' => self::BASE.'#store',
            'name' => 'La Boutique',
            'address' => ['@id' => self::BASE.'#address'],
            'url' => self::BASE.'boutique',
            'telephone' => '+15145550101',
            'image' => self::BASE.'store.jpg',
            'priceRange' => '$$',
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 45.5, 'longitude' => -73.6],
            'openingHours' => ['Mo-Fr 10:00-18:00'],
            'sameAs' => ['https://maps.example/store'],
        ], LocalBusiness::of('La Boutique', $address->reference(), self::BASE.'boutique', self::BASE.'#store', '+15145550101', self::BASE.'store.jpg', '$$', 45.5, -73.6, ['Mo-Fr 10:00-18:00'], ['https://maps.example/store'], 'Store')->toArray());
        self::assertSame(['@type' => 'LocalBusiness', 'name' => 'B', 'address' => ['@id' => self::BASE.'#address']], LocalBusiness::of('B', $address->reference(), latitude: 45.5)->toArray());
    }

    public function testAPersonAndAPlace(): void
    {
        self::assertSame([
            '@type' => 'Person',
            '@id' => self::BASE.'#ada',
            'name' => 'Ada',
            'url' => self::BASE.'ada',
            'image' => self::BASE.'ada.jpg',
            'jobTitle' => 'Engineer',
            'worksFor' => ['@id' => self::BASE.'#org'],
            'email' => 'ada@example.com',
            'sameAs' => ['https://social.example/ada'],
        ], Person::of('Ada', self::BASE.'ada', self::BASE.'#ada', self::BASE.'ada.jpg', 'Engineer', Reference::to(self::BASE.'#org'), 'ada@example.com', ['https://social.example/ada'])->toArray());
        self::assertSame(['@type' => 'Person', 'name' => 'B'], Person::of('B')->toArray());

        self::assertSame([
            '@type' => 'Place',
            '@id' => self::BASE.'#salle',
            'name' => 'Salle',
            'address' => '1 rue Principale',
            'url' => self::BASE.'salle',
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 45.0, 'longitude' => 0.0],
            'telephone' => '+15145550102',
        ], Place::of('Salle', '1 rue Principale', self::BASE.'#salle', self::BASE.'salle', 45.0, 0.0, '+15145550102')->toArray());
        self::assertSame(['@type' => 'Place'], Place::of(longitude: 3.0)->toArray());
        self::assertNull(Place::coordinates(null, null));
    }

    public function testAnEvent(): void
    {
        self::assertSame([
            '@type' => 'Festival',
            '@id' => self::BASE.'e#event',
            'name' => 'Tournoi',
            'startDate' => '2027-04-10T10:00:00-04:00',
            'endDate' => '2027-04-10',
            'location' => ['@type' => 'Place', 'name' => 'Salle'],
            'description' => 'Jeux',
            'url' => self::BASE.'e',
            'image' => self::BASE.'e.jpg',
            'eventStatus' => 'https://schema.org/EventPostponed',
            'eventAttendanceMode' => 'https://schema.org/MixedEventAttendanceMode',
            'offers' => [['@type' => 'Offer', 'price' => '10.00', 'priceCurrency' => 'CAD']],
            'organizer' => ['@id' => self::BASE.'#org'],
            'performer' => ['@type' => 'Person', 'name' => 'Ada'],
            'inLanguage' => 'fr',
        ], Event::of('Tournoi', new DateTimeImmutable('2027-04-10 10:00', new DateTimeZone('America/Toronto')), 'Salle', self::BASE.'e#event', '2027-04-10', 'Jeux', self::BASE.'e', self::BASE.'e.jpg', EventStatus::Postponed, EventAttendanceMode::Mixed, [Offer::of('10.00', 'CAD')], Reference::to(self::BASE.'#org'), Person::of('Ada'), 'fr', 'Festival')->toArray());
        self::assertSame(['@type' => 'Event', 'name' => 'A', 'startDate' => '2027-04-10', 'location' => ['@id' => self::BASE.'#salle']], Event::of('A', '2027-04-10', Reference::to(self::BASE.'#salle'))->toArray());
    }

    public function testAProductAndItsOffer(): void
    {
        self::assertSame([
            '@type' => 'Offer',
            '@id' => self::BASE.'p#offer',
            'price' => 19.99,
            'priceCurrency' => 'CAD',
            'availability' => 'https://schema.org/PreOrder',
            'url' => self::BASE.'p',
            'priceValidUntil' => '2027-01-01',
            'seller' => ['@id' => self::BASE.'#org'],
        ], Offer::of(19.99, 'CAD', self::BASE.'p#offer', ItemAvailability::PreOrder, self::BASE.'p', '2027-01-01', Reference::to(self::BASE.'#org'))->toArray());

        self::assertSame([
            '@type' => 'Product',
            '@id' => self::BASE.'p#product',
            'name' => 'T-shirt',
            'description' => 'Coton',
            'image' => self::BASE.'p.jpg',
            'sku' => 'NAQ001F-M',
            'gtin' => '0123456789012',
            'brand' => ['@type' => 'Brand', 'name' => 'Le Gnome'],
            'offers' => ['@type' => 'Offer', 'price' => 25, 'priceCurrency' => 'CAD'],
            'url' => self::BASE.'p',
        ], Product::of('T-shirt', self::BASE.'p#product', 'Coton', self::BASE.'p.jpg', 'NAQ001F-M', '0123456789012', 'Le Gnome', Offer::of(25, 'CAD'), self::BASE.'p')->toArray());
        self::assertSame(['@type' => 'Product', 'name' => 'A', 'brand' => ['@id' => self::BASE.'#brand']], Product::of('A', brand: Reference::to(self::BASE.'#brand'))->toArray());
    }

    public function testABookAndOtherWorks(): void
    {
        self::assertSame([
            '@type' => 'Book',
            '@id' => self::BASE.'b#book',
            'name' => 'Le Grimoire',
            'author' => ['@type' => 'Person', 'name' => 'Ada'],
            'isbn' => '9782000000000',
            'url' => self::BASE.'b',
            'bookFormat' => 'https://schema.org/EBook',
            'inLanguage' => 'fr',
            'numberOfPages' => 320,
            'datePublished' => '2026-10-01',
            'publisher' => ['@id' => self::BASE.'#org'],
            'image' => self::BASE.'b.jpg',
            'description' => 'Un livre',
        ], Book::of('Le Grimoire', 'Ada', self::BASE.'b#book', '9782000000000', self::BASE.'b', BookFormat::EBook, 'fr', 320, '2026-10-01', Reference::to(self::BASE.'#org'), self::BASE.'b.jpg', 'Un livre')->toArray());
        self::assertSame(['@type' => 'Book', 'name' => 'A', 'author' => [['@id' => self::BASE.'#ada']]], Book::of('A', [Reference::to(self::BASE.'#ada')])->toArray());

        self::assertSame([
            '@type' => 'Game',
            '@id' => self::BASE.'g#game',
            'name' => 'Jeu',
            'author' => ['@id' => self::BASE.'#ada'],
            'url' => self::BASE.'g',
            'description' => 'Un jeu',
            'inLanguage' => 'fr',
            'datePublished' => '2026-01-01',
            'dateModified' => '2026-02-01',
            'image' => self::BASE.'g.jpg',
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
        ], CreativeWork::of('Jeu', self::BASE.'g#game', Reference::to(self::BASE.'#ada'), self::BASE.'g', 'Un jeu', 'fr', '2026-01-01', '2026-02-01', self::BASE.'g.jpg', 'https://creativecommons.org/licenses/by/4.0/', 'Game')->toArray());
        self::assertSame(['@type' => 'CreativeWork', 'name' => 'A'], CreativeWork::of('A')->toArray());

        self::assertSame([
            '@type' => 'BlogPosting',
            '@id' => self::BASE.'a#article',
            'headline' => 'Nouvelles',
            'author' => [['@id' => self::BASE.'#ada']],
            'datePublished' => '2026-11-01',
            'dateModified' => '2026-11-02',
            'image' => [self::BASE.'a.jpg'],
            'publisher' => ['@id' => self::BASE.'#org'],
            'url' => self::BASE.'a',
            'description' => 'Des nouvelles',
            'inLanguage' => 'fr',
            'mainEntityOfPage' => ['@id' => self::BASE.'a#webpage'],
        ], Article::of('Nouvelles', self::BASE.'a#article', [Reference::to(self::BASE.'#ada')], '2026-11-01', '2026-11-02', [self::BASE.'a.jpg'], Reference::to(self::BASE.'#org'), self::BASE.'a', 'Des nouvelles', 'fr', Reference::to(self::BASE.'a#webpage'), 'BlogPosting')->toArray());
        self::assertSame(['@type' => 'Article', 'headline' => 'A'], Article::of('A')->toArray());
    }

    public function testSoftware(): void
    {
        self::assertSame([
            '@type' => 'SoftwareSourceCode',
            '@id' => self::BASE.'packages/dice#code',
            'name' => 'dice',
            'codeRepository' => 'https://git.example/dice',
            'description' => 'Dés',
            'programmingLanguage' => ['PHP'],
            'license' => 'https://spdx.org/licenses/MIT',
            'version' => '0.1.0',
            'author' => ['@id' => self::BASE.'#org'],
            'url' => self::BASE.'packages/dice',
            'runtimePlatform' => 'PHP 8.5',
            'keywords' => 'dice, games',
        ], SoftwareSourceCode::of('dice', 'https://git.example/dice', self::BASE.'packages/dice#code', 'Dés', ['PHP'], 'https://spdx.org/licenses/MIT', '0.1.0', Reference::to(self::BASE.'#org'), self::BASE.'packages/dice', 'PHP 8.5', ['dice', 'games'])->toArray());
        self::assertSame(['@type' => 'SoftwareSourceCode', 'keywords' => 'x', 'name' => 'a', 'codeRepository' => 'https://git.example/a'], SoftwareSourceCode::of('a', 'https://git.example/a', properties: ['keywords' => 'x'])->toArray());

        self::assertSame([
            '@type' => 'WebApplication',
            '@id' => self::BASE.'#app',
            'name' => 'App',
            'applicationCategory' => 'GameApplication',
            'operatingSystem' => 'Web',
            'softwareVersion' => '1.2',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'CAD'],
            'url' => self::BASE.'app',
            'description' => 'Une app',
            'downloadUrl' => self::BASE.'app.zip',
        ], SoftwareApplication::of('App', self::BASE.'#app', 'GameApplication', 'Web', '1.2', Offer::of('0', 'CAD'), self::BASE.'app', 'Une app', self::BASE.'app.zip', 'WebApplication')->toArray());
        self::assertSame(['@type' => 'SoftwareApplication', 'name' => 'A'], SoftwareApplication::of('A')->toArray());
    }

    public function testQuestionsAndImages(): void
    {
        self::assertSame([
            '@type' => 'FAQPage',
            '@id' => self::BASE.'faq#webpage',
            'url' => self::BASE.'faq',
            'mainEntity' => [
                ['@type' => 'Question', 'name' => 'Quoi ?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Ceci.']],
                ['@type' => 'Question', 'name' => 'Où ?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Ici.']],
            ],
        ], FAQPage::of([['question' => 'Quoi ?', 'answer' => 'Ceci.'], ['question' => 'Où ?', 'answer' => 'Ici.']], self::BASE.'faq', self::BASE.'faq#webpage')->toArray());
        self::assertSame(['@type' => 'FAQPage', 'name' => 'FAQ'], FAQPage::of([], properties: ['name' => 'FAQ'])->toArray());

        self::assertSame([
            '@type' => 'ImageObject',
            '@id' => self::BASE.'a.jpg#image',
            'contentUrl' => self::BASE.'a.jpg',
            'url' => self::BASE.'a',
            'caption' => 'Une image',
            'width' => 800,
            'height' => 600,
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
            'creator' => ['@id' => self::BASE.'#ada'],
            'creditText' => 'Ada',
        ], ImageObject::of(self::BASE.'a.jpg', self::BASE.'a.jpg#image', 'Une image', 800, 600, 'https://creativecommons.org/licenses/by/4.0/', Reference::to(self::BASE.'#ada'), 'Ada', self::BASE.'a')->toArray());
        self::assertSame(['@type' => 'ImageObject', 'contentUrl' => self::BASE.'b.jpg'], ImageObject::of(self::BASE.'b.jpg')->toArray());
    }

    public function testTheNamedPropertiesReplaceTheFreeOnesUnlessLeftOut(): void
    {
        self::assertSame(['a' => 1, 'b' => 3, 'd' => 'kept', 'e' => [9], 'c' => [1]], Properties::merge(['a' => 1, 'b' => 2, 'd' => 'kept', 'e' => [9]], ['b' => 3, 'c' => [1], 'd' => null, 'e' => []]));
    }

    public function testTheFactoriesGiveNodesTheValidatorAccepts(): void
    {
        $organization = Organization::of('Le Gnome', self::BASE, self::BASE.'#org', self::BASE.'logo.png');
        $graph = Graph::of(
            $organization,
            WebSite::of('Le Gnome', self::BASE, self::BASE.'#website', inLanguage: 'fr-CA', publisher: $organization->reference()),
            Event::of('Tournoi', '2027-04-10T10:00', Place::of('Salle', PostalAddress::of('1 rue', 'Montréal', 'QC', 'H2X 1Y4', 'CA')), endDate: '2027-04-10T18:00', description: 'Jeux', image: self::BASE.'e.jpg', status: EventStatus::Scheduled, offers: Offer::of('10', 'CAD', availability: ItemAvailability::InStock, url: self::BASE.'e'), organizer: $organization->reference()),
        );

        self::assertSame([], new Validator()->validate($graph)->issues);
        self::assertInstanceOf(Node::class, $graph->get(self::BASE.'#org'));
    }
}
