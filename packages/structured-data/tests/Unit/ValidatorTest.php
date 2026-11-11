<?php

declare(strict_types=1);

namespace Phil\StructuredData\Tests\Unit;

use Phil\StructuredData\Graph;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use Phil\StructuredData\Validation\Issue;
use Phil\StructuredData\Validation\Report;
use Phil\StructuredData\Validation\Rules;
use Phil\StructuredData\Validation\Severity;
use Phil\StructuredData\Validation\TypeRules;
use Phil\StructuredData\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Validator::class)]
#[CoversClass(Rules::class)]
#[CoversClass(TypeRules::class)]
#[CoversClass(Report::class)]
#[CoversClass(Issue::class)]
#[CoversClass(Severity::class)]
final class ValidatorTest extends TestCase
{
    public function testACompleteGraphHasNoIssue(): void
    {
        $report = new Validator()->validate(Graph::of(
            Node::of('Organization', ['name' => 'Le Gnome', 'url' => 'https://example.com', 'logo' => 'https://example.com/logo.png'], 'https://example.com/#org'),
            Node::of('WebSite', ['name' => 'Site', 'url' => 'https://example.com', 'inLanguage' => 'fr-CA', 'publisher' => Reference::to('https://example.com/#org')]),
        ));

        self::assertSame([], $report->issues);
        self::assertTrue($report->isValid());
        self::assertSame([], $report->toArray());
    }

    public function testMissingPropertiesAreErrorsOrWarnings(): void
    {
        $report = new Validator()->validate(Graph::of(
            Node::of('Event', ['name' => 'Tournoi', 'location' => Node::of('Place', ['url' => 'https://example.com/salle'])], 'https://example.com/e#event'),
            Node::of('Thing', ['name' => 'Plain']),
        ));

        self::assertSame([
            ['severity' => 'error', 'code' => 'missing-property', 'node' => 'https://example.com/e#event', 'property' => 'startDate', 'message' => 'https://example.com/e#event needs startDate.'],
            ['severity' => 'warning', 'code' => 'missing-recommended', 'node' => 'https://example.com/e#event', 'property' => 'endDate', 'message' => 'https://example.com/e#event should have endDate.'],
            ['severity' => 'warning', 'code' => 'missing-recommended', 'node' => 'https://example.com/e#event', 'property' => 'eventStatus', 'message' => 'https://example.com/e#event should have eventStatus.'],
            ['severity' => 'warning', 'code' => 'missing-recommended', 'node' => 'https://example.com/e#event', 'property' => 'description', 'message' => 'https://example.com/e#event should have description.'],
            ['severity' => 'warning', 'code' => 'missing-recommended', 'node' => 'https://example.com/e#event', 'property' => 'image', 'message' => 'https://example.com/e#event should have image.'],
            ['severity' => 'warning', 'code' => 'missing-recommended', 'node' => 'https://example.com/e#event', 'property' => 'offers', 'message' => 'https://example.com/e#event should have offers.'],
            ['severity' => 'warning', 'code' => 'missing-recommended', 'node' => 'https://example.com/e#event', 'property' => 'organizer', 'message' => 'https://example.com/e#event should have organizer.'],
            ['severity' => 'error', 'code' => 'missing-property', 'node' => 'https://example.com/e#event › location', 'property' => 'name', 'message' => 'https://example.com/e#event › location needs one of name, address.'],
            ['severity' => 'warning', 'code' => 'missing-recommended', 'node' => 'https://example.com/e#event › location', 'property' => 'address', 'message' => 'https://example.com/e#event › location should have address.'],
        ], $report->toArray());
        self::assertFalse($report->isValid());
        self::assertCount(2, $report->errors());
        self::assertCount(7, $report->warnings());
        self::assertSame(Severity::Error, $report->errors()[1]->severity);
        self::assertSame(Severity::Warning, $report->warnings()[6]->severity);
    }

    public function testASubtypeNeedsWhatItsParentsNeed(): void
    {
        $report = new Validator()->validate(Graph::of(Node::of(['Restaurant', 'LocalBusiness'], ['telephone' => '+15145550100', 'url' => 'https://example.com', 'image' => 'https://example.com/a.jpg', 'openingHoursSpecification' => 'x', 'geo' => 'y', 'logo' => 'https://example.com/logo.png'])));

        self::assertSame([
            'Restaurant @graph[0] needs address.',
            'Restaurant @graph[0] needs name.',
        ], array_map(static fn (Issue $issue): string => $issue->message, $report->issues));
    }

    public function testARequiredPropertyIsNotAlsoRecommended(): void
    {
        $report = new Validator([new TypeRules('Thing', ['name'], ['name', 'url', ['url'], ['image', 'logo']])])->validate(Graph::of(Node::of('Thing')));

        self::assertSame([
            'Thing @graph[0] needs name.',
            'Thing @graph[0] should have url.',
            'Thing @graph[0] should have one of image, logo.',
        ], array_map(static fn (Issue $issue): string => $issue->message, $report->issues));
        self::assertSame('image', $report->issues[2]->property);
    }

    public function testRulesAreAddedOrReplaced(): void
    {
        $validator = new Validator()->withRules(new TypeRules('Recipe', ['recipeIngredient'], parent: 'CreativeWork'), new TypeRules('Person', ['name', 'email']));
        $report = $validator->validate(Graph::of(Node::of('Recipe', ['name' => 'Soupe', 'author' => Node::of('Person', ['name' => 'Ada', 'url' => 'https://ada.example', 'sameAs' => 'https://a.example'])])));

        self::assertSame([
            'Recipe @graph[0] needs recipeIngredient.',
            'Recipe @graph[0] › author needs email.',
        ], array_map(static fn (Issue $issue): string => $issue->message, $report->issues));
        self::assertSame([], new Validator([])->validate(Graph::of(Node::of('Event')))->issues);
    }

    public function testAParentThatLoopsIsFollowedOnce(): void
    {
        $validator = new Validator([new TypeRules('A', ['a'], parent: 'B'), new TypeRules('B', ['b'], parent: 'A')]);

        self::assertSame(['A @graph[0] needs a.', 'A @graph[0] needs b.'], array_map(static fn (Issue $issue): string => $issue->message, $validator->validate(Graph::of(Node::of(['A', 'B'])))->issues));
        self::assertSame(['C @graph[0] needs a.', 'C @graph[0] needs b.'], array_map(static fn (Issue $issue): string => $issue->message, $validator->withRules(new TypeRules('C', parent: 'A'))->validate(Graph::of(Node::of('C')))->issues));
        self::assertSame(['D @graph[0] needs d.'], array_map(static fn (Issue $issue): string => $issue->message, $validator->withRules(new TypeRules('D', ['d'], parent: 'Unknown'))->validate(Graph::of(Node::of('D')))->issues));
    }

    public function testAnUntypedNodeAndADanglingReferenceAreWarnings(): void
    {
        $report = new Validator()->validate(Graph::of(
            Node::fromArray(['name' => 'Nameless', 'knows' => ['@id' => 'https://example.com/#nobody']]),
            Node::fromArray(['@id' => 'urn:typeless']),
        ));

        self::assertSame([
            ['severity' => 'warning', 'code' => 'untyped-node', 'node' => 'node @graph[0]', 'property' => null, 'message' => 'node @graph[0] has no @type: no rule applies to it.'],
            ['severity' => 'warning', 'code' => 'unresolved-reference', 'node' => 'node @graph[0]', 'property' => 'knows', 'message' => 'node @graph[0] › knows refers to "https://example.com/#nobody", which the graph lacks.'],
            ['severity' => 'warning', 'code' => 'untyped-node', 'node' => 'urn:typeless', 'property' => null, 'message' => 'urn:typeless has no @type: no rule applies to it.'],
        ], $report->toArray());
        self::assertTrue($report->isValid());
    }

    /**
     * @return iterable<string, array{string, mixed, ?string}>
     */
    public static function values(): iterable
    {
        yield 'a date' => ['startDate', '2027-04-10', null];
        yield 'a date and time' => ['startDate', '2027-04-10T19:30', null];
        yield 'with seconds and a zone' => ['endDate', '2027-04-10T19:30:15.250-04:00', null];
        yield 'in UTC' => ['datePublished', '2027-04-10T23:59:59Z', null];
        yield 'a zone without colon' => ['dateModified', '2027-04-10T00:00+0530', null];
        yield 'a day that does not exist' => ['startDate', '2027-02-30', 'invalid-date'];
        yield 'a month that does not exist' => ['startDate', '2027-13-01', 'invalid-date'];
        yield 'a year that does not exist' => ['startDate', '0000-01-01', 'invalid-date'];
        yield 'an hour that does not exist' => ['startDate', '2027-04-10T24:00', 'invalid-date'];
        yield 'a text' => ['startDate', 'next Tuesday', 'invalid-date'];
        yield 'a date then a line' => ['startDate', "2027-04-10\n", 'invalid-date'];
        yield 'a number date' => ['startDate', 20270410, null];
        yield 'an address' => ['url', 'https://example.com/a', null];
        yield 'an upper-case scheme' => ['sameAs', 'HTTP://example.com', null];
        yield 'a relative address' => ['url', '/a', 'invalid-url'];
        yield 'another scheme' => ['logo', 'ftp://example.com/a.png', 'invalid-url'];
        yield 'no host' => ['image', 'https:///a.png', 'invalid-url'];
        yield 'a space' => ['codeRepository', 'https://example.com/a b', 'invalid-url'];
        yield 'not an address at all' => ['contentUrl', 'http://:80', 'invalid-url'];
        // parse_url() took a user name for a host: this address leads to evil.example, not to example.com.
        yield 'credentials before the host' => ['sameAs', 'https://example.com@evil.example/', 'invalid-url'];
        yield 'a user and a password' => ['url', 'https://user:secret@example.com/a', 'invalid-url'];
        yield 'an IP address' => ['url', 'https://127.0.0.1/a', 'invalid-url'];
        yield 'an IP address in decimal' => ['image', 'https://2130706433/a.png', 'invalid-url'];
        yield 'an empty label' => ['url', 'https://example..com/a', 'invalid-url'];
        yield 'an escape in the host' => ['url', 'https://%zz.example/a', 'invalid-url'];
        yield 'a host of one name' => ['url', 'https://intranet/a', 'invalid-url'];
        yield 'port 0' => ['url', 'https://example.com:0/a', 'invalid-url'];
        yield 'a line at the end' => ['url', "https://example.com/a\n", 'invalid-url'];
        yield 'an international host' => ['url', 'https://bücher.example/a', null];
        yield 'dot segments' => ['url', 'https://example.com/a/%2e%2e/b', null];
        yield 'a node for an image' => ['image', Node::of('ImageObject', ['contentUrl' => 'https://example.com/a.png']), null];
        yield 'a language text' => ['url', new LanguageString('nope', 'fr'), null];
        yield 'a currency' => ['priceCurrency', 'CAD', null];
        yield 'a lower-case currency' => ['priceCurrency', 'cad', 'invalid-currency'];
        yield 'a long currency' => ['priceCurrency', 'CADX', 'invalid-currency'];
        yield 'a currency then a line' => ['priceCurrency', "CAD\n", 'invalid-currency'];
        yield 'a language' => ['inLanguage', 'fr-CA', null];
        yield 'a locale' => ['inLanguage', 'fr_CA', 'invalid-language'];
        yield 'an unchecked property' => ['name', 'next Tuesday', null];
    }

    public function testLocalhostIsAnAddressOnlyWhenAllowed(): void
    {
        $codes = static fn (Validator $validator, string $url): array => array_map(static fn (Issue $issue): string => $issue->code, $validator->validate(Graph::of(Node::of('Thing', ['url' => $url])))->issues);

        foreach (['http://localhost:8000/a', 'https://app.localhost/a'] as $url) {
            self::assertSame(['invalid-url'], $codes(new Validator([]), $url), $url);
            self::assertSame([], $codes(new Validator([], allowLocalhost: true), $url), $url);
            self::assertSame([], $codes(new Validator([], allowLocalhost: true)->withRules(), $url), $url);
        }

        foreach (['http://127.0.0.1/a', 'http://localhostx/a', 'http://intranet/a'] as $url) {
            self::assertSame(['invalid-url'], $codes(new Validator([], allowLocalhost: true), $url), $url);
        }
    }

    #[DataProvider('values')]
    public function testValuesAreReadInTheirFormat(string $property, mixed $value, ?string $code): void
    {
        $report = new Validator([])->validate(Graph::of(Node::of('Thing', [$property => [$value, $value]])));
        $codes = array_map(static fn (Issue $issue): string => $issue->code, $report->issues);

        self::assertSame(null === $code ? [] : [$code, $code], $codes);

        if (null !== $code) {
            self::assertSame(Severity::Error, $report->issues[0]->severity);
            self::assertSame($property, $report->issues[1]->property);
            self::assertSame('Thing @graph[0]', $report->issues[1]->node);
            self::assertStringStartsWith('Thing @graph[0] › '.$property.'[1] is ', $report->issues[1]->message);
            self::assertStringEndsWith(\sprintf('; "%s" is not.', \is_string($value) ? $value : ''), $report->issues[1]->message);
        }
    }

    public function testASingleValueIsNamedWithoutAPosition(): void
    {
        $report = new Validator([])->validate(Graph::of(Node::of('Thing', ['url' => '/a'])));

        self::assertSame('Thing @graph[0] › url is an absolute HTTP or HTTPS address; "/a" is not.', $report->issues[0]->message);
    }

    public function testTheShippedRulesCoverTheCommonTypes(): void
    {
        $rules = [];

        foreach (Rules::schemaOrg() as $rule) {
            $rules[$rule->type] = $rule;
        }

        foreach (['WebSite', 'WebPage', 'BreadcrumbList', 'Organization', 'LocalBusiness', 'Person', 'Event', 'Place', 'PostalAddress', 'Product', 'Offer', 'Book', 'CreativeWork', 'Article', 'SoftwareSourceCode', 'SoftwareApplication', 'FAQPage', 'ImageObject', 'Question', 'Answer', 'ListItem'] as $type) {
            self::assertArrayHasKey($type, $rules);
        }

        self::assertSame('Organization', $rules['LocalBusiness']->parent);
        self::assertSame('LocalBusiness', $rules['Store']->parent);
        self::assertSame('Event', $rules['Festival']->parent);
        self::assertSame('Article', $rules['BlogPosting']->parent);
        self::assertSame('WebPage', $rules['ContactPage']->parent);
        self::assertSame('SoftwareApplication', $rules['VideoGame']->parent);
        self::assertSame([], $rules['VideoGame']->required);
        self::assertSame([], $rules['VideoGame']->recommended);
        self::assertSame([['name', 'address']], $rules['Place']->required);
        self::assertCount(49, $rules);

        $table = [];

        foreach (['CreativeWork', 'WebSite', 'WebPage', 'FAQPage', 'Question', 'Answer', 'BreadcrumbList', 'ListItem', 'Organization', 'LocalBusiness', 'Person', 'Event', 'Place', 'PostalAddress', 'Product', 'Offer', 'Book', 'Article', 'SoftwareSourceCode', 'SoftwareApplication', 'ImageObject'] as $type) {
            $table[$type] = [$rules[$type]->required, $rules[$type]->recommended, $rules[$type]->parent];
        }

        self::assertSame([
            'CreativeWork' => [[], ['name', 'author'], null],
            'WebSite' => [['name', 'url'], ['inLanguage', 'publisher'], null],
            'WebPage' => [['url'], ['name', 'inLanguage', 'isPartOf'], null],
            'FAQPage' => [['mainEntity'], [], 'WebPage'],
            'Question' => [['name', 'acceptedAnswer'], [], null],
            'Answer' => [['text'], [], null],
            'BreadcrumbList' => [['itemListElement'], [], null],
            'ListItem' => [['position', 'name'], ['item'], null],
            'Organization' => [['name'], ['url', 'logo'], null],
            'LocalBusiness' => [['address'], ['telephone', 'url', 'image', 'openingHoursSpecification', 'geo'], 'Organization'],
            'Person' => [['name'], ['url', 'sameAs'], null],
            'Event' => [['name', 'startDate', 'location'], ['endDate', 'eventStatus', 'description', 'image', 'offers', 'organizer'], null],
            'Place' => [[['name', 'address']], ['address'], null],
            'PostalAddress' => [[], ['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'], null],
            'Product' => [['name', ['offers', 'review', 'aggregateRating']], ['image', 'description', 'sku', 'brand'], null],
            'Offer' => [['price', 'priceCurrency'], ['availability', 'url'], null],
            'Book' => [['name', 'author'], ['isbn', 'url', 'inLanguage', 'bookFormat'], 'CreativeWork'],
            'Article' => [['headline'], ['author', 'datePublished', 'dateModified', 'image', 'publisher'], 'CreativeWork'],
            'SoftwareSourceCode' => [['name', 'codeRepository'], ['programmingLanguage', 'license', 'description', 'version'], 'CreativeWork'],
            'SoftwareApplication' => [['name'], ['applicationCategory', 'operatingSystem', 'offers', 'softwareVersion'], null],
            'ImageObject' => [[['contentUrl', 'url']], ['caption', 'width', 'height', 'license', 'creator'], null],
        ], $table);
    }
}
