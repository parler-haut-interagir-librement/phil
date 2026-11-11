<?php

declare(strict_types=1);

namespace Phil\StructuredData\Tests\Unit;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\Id;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\LanguageTag;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use Phil\StructuredData\Type\EventStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Node::class)]
#[CoversClass(Reference::class)]
#[CoversClass(LanguageString::class)]
#[CoversClass(LanguageTag::class)]
#[CoversClass(Id::class)]
#[CoversClass(InvalidStructuredData::class)]
final class NodeTest extends TestCase
{
    public function testANodeHoldsItsTypesItsIdAndItsProperties(): void
    {
        $node = Node::of('Organization', [
            'name' => 'Le Gnome',
            'foundingDate' => new DateTimeImmutable('2020-03-01 10:00:00', new DateTimeZone('America/Toronto')),
            'eventStatus' => EventStatus::Scheduled,
            'numberOfEmployees' => 3,
            'ratio' => 1.0,
            'isAccessibleForFree' => false,
            'alternateName' => new LanguageString('The Gnome', 'en_ca'),
            'parentOrganization' => Reference::to('https://example.com/#parent'),
            'sameAs' => ['https://a.example', null, 'https://b.example'],
            'logo' => null,
            'keywords' => [],
        ], 'https://example.com/#organization');

        self::assertSame(['Organization'], $node->types);
        self::assertSame('https://example.com/#organization', $node->id);
        self::assertSame([
            '@type' => 'Organization',
            '@id' => 'https://example.com/#organization',
            'name' => 'Le Gnome',
            'foundingDate' => '2020-03-01T10:00:00-05:00',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'numberOfEmployees' => 3,
            'ratio' => 1.0,
            'isAccessibleForFree' => false,
            'alternateName' => ['@value' => 'The Gnome', '@language' => 'en-CA'],
            'parentOrganization' => ['@id' => 'https://example.com/#parent'],
            'sameAs' => ['https://a.example', 'https://b.example'],
        ], $node->toArray());
        self::assertTrue($node->has('name'));
        self::assertFalse($node->has('logo'));
        self::assertSame('Le Gnome', $node->get('name'));
        self::assertNull($node->get('logo'));
        self::assertTrue($node->isA('Organization'));
        self::assertFalse($node->isA('Person'));
        self::assertSame('https://example.com/#organization', $node->reference()->id);
        self::assertSame(1, $node->depth);
        self::assertFalse($node->nestsIdentified);
    }

    public function testSeveralTypesAreWrittenAsAList(): void
    {
        $node = Node::of(['Book', 'Product', 'Book'], ['name' => 'Le Grimoire']);

        self::assertSame(['@type' => ['Book', 'Product'], 'name' => 'Le Grimoire'], $node->toArray());
        self::assertSame(['@type' => ['Book', 'Product', 'Game', 'Toy'], 'name' => 'Le Grimoire'], $node->withType('Game', 'Book', 'Toy')->toArray());
        self::assertNull($node->id);
    }

    public function testANodeNeverChanges(): void
    {
        $node = Node::of('Person', ['name' => 'Ada', 'url' => 'https://ada.example']);
        $changed = $node->with('jobTitle', 'Engineer')->with('url', null)->withId('https://ada.example/#me');

        self::assertSame(['@type' => 'Person', 'name' => 'Ada', 'url' => 'https://ada.example'], $node->toArray());
        self::assertSame(['@type' => 'Person', '@id' => 'https://ada.example/#me', 'name' => 'Ada', 'jobTitle' => 'Engineer'], $changed->toArray());
        self::assertSame(['@type' => 'Person', 'name' => 'Ada'], $node->without('url')->toArray());
        self::assertSame(['@type' => 'Person', 'name' => 'Ada', 'url' => 'https://ada.example'], $node->without('missing')->toArray());
        self::assertNull($changed->withId(null)->id);
        self::assertSame(['@type' => 'Person', 'url' => 'https://ada.example', 'name' => 'Grace'], $node->withProperties(['name' => 'Grace'])->toArray());
        self::assertSame(['@type' => 'Person', 'url' => 'https://ada.example'], $node->withProperties(['name' => []])->toArray());
    }

    public function testTwoNodesOfOneThingMerge(): void
    {
        $first = Node::of('Organization', ['name' => 'Le Gnome', 'url' => 'https://example.com'], 'https://example.com/#org');
        $second = Node::of(['LocalBusiness', 'Organization'], ['telephone' => '+15145550100', 'name' => 'Le Gnome Archiviste']);

        self::assertSame([
            '@type' => ['Organization', 'LocalBusiness'],
            '@id' => 'https://example.com/#org',
            'name' => 'Le Gnome Archiviste',
            'url' => 'https://example.com',
            'telephone' => '+15145550100',
        ], $first->mergedWith($second)->toArray());
        self::assertSame('https://example.com/#org', $second->mergedWith($first)->id);
        self::assertSame(['A', 'B', 'C', 'D'], Node::of(['A', 'B'])->mergedWith(Node::of(['C', 'D']))->types);
        self::assertSame('https://example.com/#org', $first->mergedWith($second->withId('https://example.com/#other'))->id);
    }

    public function testNestedNodesAreCountedAndTheIdentifiedOnesMarked(): void
    {
        $address = Node::of('PostalAddress', ['addressCountry' => 'CA']);
        $place = Node::of('Place', ['address' => $address]);
        $identified = Node::of('Organization', ['name' => 'A'], 'https://example.com/#a');

        self::assertSame(2, $place->depth);
        self::assertFalse($place->nestsIdentified);
        self::assertSame(3, Node::of('Event', ['location' => [$place, 'online']])->depth);
        self::assertTrue(Node::of('Event', ['organizer' => [$identified]])->nestsIdentified);
        self::assertTrue(Node::of('Event', ['x' => 'y', 'location' => Node::of('Place', ['owner' => $identified])])->nestsIdentified);
        self::assertTrue(Node::of('Event', ['organizer' => $identified, 'location' => $place])->nestsIdentified);
    }

    public function testNodesNestThirtyTwoLevelsAtMost(): void
    {
        $node = Node::of('Thing');

        for ($level = 2; $level <= Node::MAX_DEPTH; ++$level) {
            $node = Node::of('Thing', ['part' => $node]);
        }

        self::assertSame(32, $node->depth);
        $this->expectExceptionObject(InvalidStructuredData::because('Nodes nest 32 levels deep at most.'));
        Node::of('Thing', ['part' => $node]);
    }

    public function testDataBecomesANode(): void
    {
        $node = Node::fromArray([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            '@id' => 'https://example.com/e#event',
            'name' => ['@value' => 'Tournoi', '@language' => 'fr'],
            'alternateName' => ['@value' => 'Tournament'],
            'startDate' => ['@value' => '2027-04-10', '@type' => 'Date'],
            'location' => ['@type' => 'Place', 'name' => 'Salle', 'address' => ['addressCountry' => 'CA']],
            'organizer' => ['@id' => 'https://example.com/#org'],
            'performer' => [['@type' => 'Person', '@id' => 'https://example.com/#p', 'name' => 'P']],
            'offers' => [],
        ]);

        self::assertSame([
            '@type' => 'Event',
            '@id' => 'https://example.com/e#event',
            'name' => ['@value' => 'Tournoi', '@language' => 'fr'],
            'alternateName' => 'Tournament',
            'startDate' => '2027-04-10',
            'location' => ['@type' => 'Place', 'name' => 'Salle', 'address' => ['addressCountry' => 'CA']],
            'organizer' => ['@id' => 'https://example.com/#org'],
            'performer' => [['@type' => 'Person', '@id' => 'https://example.com/#p', 'name' => 'P']],
        ], $node->toArray());
        self::assertInstanceOf(Reference::class, $node->get('organizer'));
        self::assertSame([], Node::fromArray(['name' => 'x'])->types);
        self::assertSame(['name' => 'x'], Node::fromArray(['name' => 'x'])->toArray());
        self::assertSame(['@type' => ['A', 'B']], Node::fromArray(['@type' => ['A', 'B']])->toArray());
        self::assertInstanceOf(Node::class, Node::fromArray(['@type' => 'A', 'x' => ['@id' => 'https://e.example', 'name' => 'n']])->get('x'));
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function refused(): iterable
    {
        $term = 'A property is named by a schema.org term or an IRI, never by a JSON-LD keyword; ';
        $value = 'A property holds a text, a number, a boolean, a date, a backed enum, a language string, a reference, a node or a list of them; ';

        yield 'no type' => [static fn (): Node => Node::of([]), 'A node built with of() has at least one type.'];
        yield 'a bad type' => [static fn (): Node => Node::of('Local Business'), 'A type is a schema.org term or an IRI; "Local Business" is not.'];
        yield 'a type that is a keyword' => [static fn (): Node => Node::of('@id'), 'A type is a schema.org term or an IRI; "@id" is not.'];
        yield 'a type that is no text' => [static fn (): Node => Node::fromArray(['@type' => [3]]), 'A type is a schema.org term or an IRI; int is not.'];
        yield 'a long type' => [static fn (): Node => Node::of(str_repeat('a', 257)), 'A type is a schema.org term or an IRI; "'.str_repeat('a', 257).'" is not.'];
        yield 'a keyword property' => [static fn (): Node => Node::of('Thing', ['@graph' => []]), $term.'"@graph" is not.'];
        yield 'a numbered property' => [static fn (): Node => Node::fromArray(['@type' => 'Thing', 0 => 'zero']), $term.'"0" is not.'];
        yield 'a property after a digit' => [static fn (): Node => Node::of('Thing', ['3d' => 'x']), $term.'"3d" is not.'];
        yield 'a type after a digit' => [static fn (): Node => Node::of('3DModel'), 'A type is a schema.org term or an IRI; "3DModel" is not.'];
        yield 'a spaced property' => [static fn (): Node => Node::of('Thing', ['first name' => 'x']), $term.'"first name" is not.'];
        yield 'a property with a quote' => [static fn (): Node => Node::of('Thing', ['a"b' => 'x']), $term.'"a"b" is not.'];
        yield 'a property in bad UTF-8' => [static fn (): Node => Node::of('Thing', ["\xff" => 'x']), $term."\"\xff\" is not."];
        yield 'an object' => [static fn (): Node => Node::of('Thing', ['x' => new stdClass()]), $value.'stdClass is not.'];
        yield 'an infinite number' => [static fn (): Node => Node::of('Thing', ['x' => \INF]), 'A number of a node is finite.'];
        yield 'not a number' => [static fn (): Node => Node::of('Thing', ['x' => \NAN]), 'A number of a node is finite.'];
        yield 'a list of lists' => [static fn (): Node => Node::of('Thing', ['x' => [['a']]]), 'A list of a property holds values, not other lists.'];
        yield 'a bad id' => [static fn (): Node => Node::of('Thing', id: 'https://example.com/a b'), 'An identifier is an IRI of 1 to 2048 characters, without spaces nor control characters; "https://example.com/a b" is not.'];
        yield 'an empty id' => [static fn (): Node => Node::of('Thing')->withId(''), 'An identifier is an IRI of 1 to 2048 characters, without spaces nor control characters; "" is not.'];
        yield 'a type map' => [static fn (): Node => Node::fromArray(['@type' => ['a' => 'A']]), 'The @type of a node is a text or a list of texts, and its @id a text.'];
        yield 'a numeric type' => [static fn (): Node => Node::fromArray(['@type' => 3]), 'The @type of a node is a text or a list of texts, and its @id a text.'];
        yield 'a numeric id' => [static fn (): Node => Node::fromArray(['@type' => 'A', '@id' => 3]), 'The @type of a node is a text or a list of texts, and its @id a text.'];
        yield 'a numeric reference' => [static fn (): Node => Node::fromArray(['x' => ['@id' => 5]]), 'The @type of a node is a text or a list of texts, and its @id a text.'];
        yield 'an unknown keyword' => [static fn (): Node => Node::fromArray(['@type' => 'A', '@reverse' => []]), $term.'"@reverse" is not.'];
        yield 'a value that is no text' => [static fn (): Node => Node::fromArray(['x' => ['@value' => 3]]), 'A value object holds a text in @value, maybe its @language, and nothing else.'];
        yield 'a language that is no text' => [static fn (): Node => Node::fromArray(['x' => ['@value' => 'a', '@language' => 3]]), 'A value object holds a text in @value, maybe its @language, and nothing else.'];
        yield 'a value with more' => [static fn (): Node => Node::fromArray(['x' => ['@value' => 'a', 'name' => 'b']]), 'A value object holds a text in @value, maybe its @language, and nothing else.'];
        yield 'a bad language' => [static fn (): Node => Node::fromArray(['x' => ['@value' => 'a', '@language' => 'french!']]), 'A language is a BCP 47 tag, such as "fr" or "fr-CA"; "french!" is not.'];
        yield 'a reference to nothing' => [static fn (): Reference => Node::of('Thing')->reference(), 'Only a node with an @id can be referred to.'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('refused')]
    public function testWhatANodeCannotHoldIsRefused(Closure $build, string $message): void
    {
        $this->expectExceptionObject(InvalidStructuredData::because($message));
        $build();
    }

    public function testTermsAndIdentifiersMayBeIris(): void
    {
        $node = Node::of('https://schema.org/Thing', ['https://schema.org/name' => 'x', 'ex:prop' => 'y', 'é' => 'z', '_private' => 0, 'a'.str_repeat('b', 255) => 1], '_:b0');

        self::assertSame(['https://schema.org/Thing'], $node->types);
        self::assertSame('_:b0', $node->id);
        self::assertCount(5, $node->properties());
        self::assertSame(str_repeat('a', Id::MAX_LENGTH), Reference::to(str_repeat('a', Id::MAX_LENGTH))->id);
        self::assertSame('#me', new Reference('#me')->toArray()['@id']);
    }

    public function testAnIdentifierIsTooLongPastItsLimit(): void
    {
        $this->expectException(InvalidStructuredData::class);
        Reference::to(str_repeat('a', Id::MAX_LENGTH + 1));
    }

    public function testStableIdentifiersAreMadeOfAnAddressAndAFragment(): void
    {
        self::assertSame('https://example.com/#organization', Id::of('https://example.com/', 'organization'));
        self::assertSame('https://example.com/page?x=1#webpage', Id::of('https://example.com/page?x=1#top', 'webpage'));
        self::assertSame('https://example.com/#a.b_c~d-1', Id::of('https://example.com/', 'a.b_c~d-1'));
        self::assertSame('https://example.com/#'.str_repeat('f', 128), Id::of('https://example.com/', str_repeat('f', 128)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badFragments(): iterable
    {
        yield 'empty' => ['https://example.com/', ''];
        yield 'spaced' => ['https://example.com/', 'a b'];
        yield 'with a hash' => ['https://example.com/', 'a#b'];
        yield 'too long' => ['https://example.com/', str_repeat('f', 129)];
        yield 'after a line' => ['https://example.com/', "a\nb"];
    }

    #[DataProvider('badFragments')]
    public function testABadFragmentIsRefused(string $url, string $fragment): void
    {
        $this->expectExceptionObject(InvalidStructuredData::because(\sprintf('The fragment of an identifier is 1 to 128 letters, digits, ".", "_", "~" or "-"; "%s" is not.', $fragment)));
        Id::of($url, $fragment);
    }

    public function testAnAddressWithoutAnythingBeforeItsFragmentIsRefused(): void
    {
        $this->expectException(InvalidStructuredData::class);
        Id::of('#top', 'webpage');
    }

    public function testLanguageTagsAreWrittenInTheirUsualCase(): void
    {
        self::assertSame('fr', LanguageTag::normalize('FR'));
        self::assertSame('fr-CA', LanguageTag::normalize('fr_ca'));
        self::assertSame('zh-Hant-TW', LanguageTag::normalize('ZH_hANT_tw'));
        self::assertSame('es-419', LanguageTag::normalize('es-419'));
        self::assertSame('ast', LanguageTag::normalize('ast'));
        self::assertSame('de-CH-1996', LanguageTag::normalize('de-ch-1996'));
        self::assertSame('sl-rozaj-biske', LanguageTag::normalize('sl-ROZAJ-BISKE'));
        self::assertTrue(LanguageTag::isValid('fr-CA'));
        self::assertFalse(LanguageTag::isValid('fr_CA'));
        self::assertFalse(LanguageTag::isValid("fr\n"));
        self::assertFalse(LanguageTag::isValid('x-fr'));
        self::assertSame(['@value' => 'Bonjour', '@language' => 'fr-CA'], new LanguageString('Bonjour', 'fr_CA')->toArray());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badLanguages(): iterable
    {
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fren'];
        yield 'a short variant' => ['fr-abc1'];
        yield 'a trailing dash' => ['fr-'];
        yield 'a leading dash' => ['-fr'];
        yield 'after a line' => ["fr\nCA"];
    }

    #[DataProvider('badLanguages')]
    public function testABadLanguageIsRefused(string $tag): void
    {
        $this->expectExceptionObject(InvalidStructuredData::because(\sprintf('A language is a BCP 47 tag, such as "fr" or "fr-CA"; "%s" is not.', $tag)));
        LanguageTag::normalize($tag);
    }
}
