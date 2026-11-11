<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\JsonLd;

use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\JsonLd\ActiveContext;
use Phil\OpenBadges\JsonLd\BundledContexts;
use Phil\OpenBadges\JsonLd\ContextProcessor;
use Phil\OpenBadges\JsonLd\Conversion;
use Phil\OpenBadges\JsonLd\TermDefining;
use Phil\OpenBadges\JsonLd\TermDefinition;
use Phil\OpenBadges\JsonLd\ToRdf;
use Phil\OpenBadges\Rdf\Canonicalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * JSON-LD documents read as RDF: each feature of the subset, and each refusal of what is outside it.
 */
#[CoversClass(ToRdf::class)]
#[CoversClass(Conversion::class)]
#[CoversClass(ContextProcessor::class)]
#[CoversClass(ActiveContext::class)]
#[CoversClass(TermDefinition::class)]
#[CoversClass(TermDefining::class)]
#[CoversClass(BundledContexts::class)]
final class ToRdfTest extends TestCase
{
    private const string EX = 'https://example.org/vocab#';

    private const string XSD = 'http://www.w3.org/2001/XMLSchema#';

    private const array ALIASES = ['id' => '@id', 'type' => '@type'];

    public function testAVocabularyAndCompactIris(): void
    {
        self::assertSame(
            "<https://example.org/a> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <https://example.org/vocab#Thing> .\n"
            ."<https://example.org/a> <https://example.org/vocab#name> \"A\" .\n"
            ."<https://example.org/a> <https://schema.org/knows> <https://example.org/b> .\n",
            $this->canonical(['@context' => [...self::ALIASES, '@vocab' => self::EX, 'schema' => 'https://schema.org/', 'knows' => ['@id' => 'schema:knows', '@type' => '@id']], 'id' => 'https://example.org/a', 'type' => 'Thing', 'name' => 'A', 'knows' => 'https://example.org/b']),
        );
    }

    public function testKeywordsAreUsedDirectly(): void
    {
        self::assertSame(
            "<https://example.org/a> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <https://example.org/vocab#Thing> .\n",
            $this->canonical(['@context' => ['@vocab' => self::EX], '@id' => 'https://example.org/a', '@type' => ['Thing']]),
        );
    }

    public function testAPrefixNeedsItsFlag(): void
    {
        $context = ['@vocab' => self::EX, 'simple' => 'https://simple.example/', 'expanded' => ['@id' => 'https://expanded.example/'], 'flagged' => ['@id' => 'https://flagged.example/', '@prefix' => true], 'nodelim' => 'https://nodelim.example/x'];

        self::assertSame(
            "_:c14n0 <expanded:p> \"2\" .\n_:c14n0 <https://flagged.example/p> \"3\" .\n_:c14n0 <https://simple.example/p> \"1\" .\n_:c14n0 <nodelim:p> \"4\" .\n",
            $this->canonical(['@context' => $context, 'simple:p' => '1', 'expanded:p' => '2', 'flagged:p' => '3', 'nodelim:p' => '4']),
        );
    }

    public function testATermWhoseNameIsACompactIriOrAnIri(): void
    {
        self::assertSame(
            "_:c14n0 <https://example.org/vocab#p> <https://example.org/o> .\n_:c14n0 <https://x.example/q> \"v\" .\n",
            $this->canonical(['@context' => ['ex' => self::EX, 'ex:p' => ['@type' => '@id'], 'https://x.example/q' => ['@type' => self::XSD.'string']], 'ex:p' => 'https://example.org/o', 'https://x.example/q' => 'v']),
        );
    }

    public function testATypeScopedContextStopsAtTheNextNode(): void
    {
        $context = [...self::ALIASES, '@vocab' => self::EX, 'Outer' => ['@context' => ['label' => 'https://scoped.example/label']]];

        self::assertSame(
            "_:c14n0 <https://example.org/vocab#label> \"inner\" .\n"
            ."_:c14n1 <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <https://example.org/vocab#Outer> .\n"
            ."_:c14n1 <https://example.org/vocab#child> _:c14n0 .\n"
            ."_:c14n1 <https://scoped.example/label> \"outer\" .\n",
            $this->canonical(['@context' => $context, 'type' => 'Outer', 'label' => 'outer', 'child' => ['label' => 'inner']]),
        );
    }

    public function testAReferenceKeepsTheTypeScopedContext(): void
    {
        $context = ['@vocab' => self::EX, 'Outer' => ['@context' => ['ref' => ['@id' => 'https://scoped.example/ref'], 'id' => '@id']]];

        self::assertSame(
            "_:c14n0 <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <https://example.org/vocab#Outer> .\n_:c14n0 <https://scoped.example/ref> <https://example.org/r> .\n",
            $this->canonical(['@context' => $context, '@type' => 'Outer', 'ref' => ['id' => 'https://example.org/r']]),
        );
    }

    public function testAPropertyScopedContextReachesTheNestedNodes(): void
    {
        $context = ['@vocab' => self::EX, 'child' => ['@context' => ['label' => 'https://scoped.example/label']]];

        self::assertSame(
            "_:c14n0 <https://example.org/vocab#child> _:c14n2 .\n_:c14n1 <https://scoped.example/label> \"deep\" .\n_:c14n2 <https://example.org/vocab#more> _:c14n1 .\n_:c14n2 <https://scoped.example/label> \"inner\" .\n",
            $this->canonical(['@context' => $context, 'child' => ['label' => 'inner', 'more' => ['label' => 'deep']]]),
        );
    }

    public function testAPropertyScopedContextAppliesToAScalar(): void
    {
        $context = ['@vocab' => self::EX, 'purpose' => ['@type' => '@vocab', '@context' => ['assert' => 'https://scoped.example/assert']]];

        self::assertSame(
            "_:c14n0 <https://example.org/vocab#purpose> <https://example.org/vocab#other> .\n_:c14n0 <https://example.org/vocab#purpose> <https://scoped.example/assert> .\n",
            $this->canonical(['@context' => $context, 'purpose' => ['assert', 'other']]),
        );
    }

    public function testTypesAreAppliedInLexicographicOrder(): void
    {
        $context = [...self::ALIASES, '@vocab' => self::EX, 'B' => ['@context' => ['label' => 'https://b.example/label']], 'A' => ['@context' => ['label' => 'https://a.example/label']]];

        self::assertStringContainsString('<https://b.example/label> "x"', $this->canonical(['@context' => $context, 'type' => ['B', 'A'], 'label' => 'x']));
    }

    public function testAnEmbeddedContextChangesTheTerms(): void
    {
        self::assertSame(
            "_:c14n0 <https://example.org/vocab#child> _:c14n1 .\n_:c14n1 <https://other.example/name> \"x\" .\n",
            $this->canonical(['@context' => ['@vocab' => self::EX], 'child' => ['@context' => ['@vocab' => 'https://other.example/'], 'name' => 'x']]),
        );
    }

    public function testANullContextResetsTheTerms(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The property "name" expands to no absolute IRI in the contexts of the document.'));
        $this->canonical(['@context' => [['@vocab' => self::EX], null], 'name' => 'x']);
    }

    public function testAContextWithoutPropagationStopsAtTheNextNode(): void
    {
        self::assertSame(
            "_:c14n0 <https://example.org/vocab#child> _:c14n2 .\n_:c14n1 <https://first.example/child> _:c14n0 .\n_:c14n2 <https://first.example/label> \"inner\" .\n",
            $this->canonical(['@context' => ['@vocab' => 'https://first.example/'], 'child' => ['@context' => ['@vocab' => self::EX, '@propagate' => false], 'child' => ['label' => 'inner']]]),
        );
    }

    public function testLiterals(): void
    {
        $context = ['@vocab' => self::EX, 'double' => ['@type' => self::XSD.'double'], 'flag' => ['@type' => self::XSD.'boolean'], 'plain' => ['@type' => '@none'], 'link' => ['@type' => '@id']];

        self::assertSame(
            "_:c14n0 <https://example.org/vocab#b> \"false\"^^<http://www.w3.org/2001/XMLSchema#boolean> .\n"
            ."_:c14n0 <https://example.org/vocab#b> \"true\"^^<http://www.w3.org/2001/XMLSchema#boolean> .\n"
            ."_:c14n0 <https://example.org/vocab#big> \"1.0E21\"^^<http://www.w3.org/2001/XMLSchema#double> .\n"
            ."_:c14n0 <https://example.org/vocab#double> \"5.0E0\"^^<http://www.w3.org/2001/XMLSchema#double> .\n"
            ."_:c14n0 <https://example.org/vocab#flag> \"true\"^^<http://www.w3.org/2001/XMLSchema#boolean> .\n"
            ."_:c14n0 <https://example.org/vocab#fraction> \"-1.5E-5\"^^<http://www.w3.org/2001/XMLSchema#double> .\n"
            ."_:c14n0 <https://example.org/vocab#fraction> \"2.5E0\"^^<http://www.w3.org/2001/XMLSchema#double> .\n"
            ."_:c14n0 <https://example.org/vocab#integral> \"-3\"^^<http://www.w3.org/2001/XMLSchema#integer> .\n"
            ."_:c14n0 <https://example.org/vocab#integral> \"1\"^^<http://www.w3.org/2001/XMLSchema#integer> .\n"
            ."_:c14n0 <https://example.org/vocab#integral> \"100000000000000000000\"^^<http://www.w3.org/2001/XMLSchema#integer> .\n"
            ."_:c14n0 <https://example.org/vocab#link> \"7\"^^<http://www.w3.org/2001/XMLSchema#integer> .\n"
            ."_:c14n0 <https://example.org/vocab#plain> \"p\" .\n",
            $this->canonical(['@context' => $context, 'b' => [true, false], 'integral' => [1, -3.0, 1e20], 'fraction' => [2.5, -0.000015], 'big' => 1e21, 'double' => 5, 'flag' => true, 'plain' => 'p', 'link' => 7]),
        );
    }

    public function testValueObjects(): void
    {
        self::assertSame(
            "_:c14n0 <https://example.org/vocab#v> \"chat\"@fr .\n"
            ."_:c14n0 <https://example.org/vocab#v> \"d\"^^<https://example.org/vocab#Date> .\n"
            ."_:c14n0 <https://example.org/vocab#v> \"s\" .\n",
            $this->canonical(['@context' => ['@vocab' => self::EX], 'v' => [['@value' => 'chat', '@language' => 'fr'], ['@value' => 'd', '@type' => 'Date'], ['@value' => 's'], ['@value' => null], null]]),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedValues(): iterable
    {
        yield 'an extra key' => [['@value' => 'x', '@index' => 'i']];
        yield 'a type and a language' => [['@value' => 'x', '@type' => 'T', '@language' => 'fr']];
        yield 'an object' => [['@value' => ['x' => 1]]];
        yield 'a type that is not a string' => [['@value' => 'x', '@type' => 3]];
        yield 'a language that is not a string' => [['@value' => 'x', '@language' => 3]];
        yield 'a language on a number' => [['@value' => 3, '@language' => 'fr']];
    }

    /**
     * @param array<string, mixed> $value
     */
    #[DataProvider('malformedValues')]
    public function testAMalformedValueObjectIsRefused(array $value): void
    {
        $this->expectException(InvalidCredential::class);
        $this->canonical(['@context' => ['@vocab' => self::EX], 'v' => $value]);
    }

    public function testAValueTypeIsAnAbsoluteIri(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The type "Date" of a JSON-LD value expands to no absolute IRI.'));
        $this->canonical(['@context' => ['v' => self::EX.'v'], 'v' => ['@value' => 'd', '@type' => 'Date']]);
    }

    public function testLists(): void
    {
        self::assertSame(
            "_:c14n0 <http://www.w3.org/1999/02/22-rdf-syntax-ns#first> \"a\" .\n"
            ."_:c14n0 <http://www.w3.org/1999/02/22-rdf-syntax-ns#rest> _:c14n1 .\n"
            ."_:c14n1 <http://www.w3.org/1999/02/22-rdf-syntax-ns#first> \"b\" .\n"
            ."_:c14n1 <http://www.w3.org/1999/02/22-rdf-syntax-ns#rest> <http://www.w3.org/1999/02/22-rdf-syntax-ns#nil> .\n"
            ."_:c14n2 <http://www.w3.org/1999/02/22-rdf-syntax-ns#first> \"x\" .\n"
            ."_:c14n2 <http://www.w3.org/1999/02/22-rdf-syntax-ns#rest> <http://www.w3.org/1999/02/22-rdf-syntax-ns#nil> .\n"
            ."_:c14n3 <https://example.org/vocab#empty> <http://www.w3.org/1999/02/22-rdf-syntax-ns#nil> .\n"
            ."_:c14n3 <https://example.org/vocab#items> _:c14n0 .\n"
            ."_:c14n3 <https://example.org/vocab#one> _:c14n2 .\n",
            $this->canonical(['@context' => ['@vocab' => self::EX, 'items' => ['@container' => '@list'], 'empty' => ['@container' => ['@list']]], 'items' => ['a', null, 'b'], 'empty' => [], 'one' => ['@list' => 'x']]),
        );
    }

    public function testAGraphContainer(): void
    {
        self::assertSame(
            "_:c14n0 <https://example.org/vocab#proof> _:c14n1 .\n_:c14n2 <https://example.org/vocab#name> \"inside\" _:c14n1 .\n",
            $this->canonical(['@context' => ['@vocab' => self::EX, 'proof' => ['@container' => ['@graph', '@set']]], 'proof' => ['name' => 'inside']]),
        );
    }

    public function testBlankNodeIdentifiers(): void
    {
        self::assertSame(
            "_:c14n0 <https://example.org/vocab#knows> _:c14n1 .\n_:c14n1 <https://example.org/vocab#knows> _:c14n0 .\n",
            $this->canonical(['@context' => [...self::ALIASES, '@vocab' => self::EX, 'knows' => ['@type' => '@id']], 'id' => '_:a', 'knows' => ['@id' => '_:b', 'knows' => '_:a']]),
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        $vocab = [...self::ALIASES, '@vocab' => self::EX];

        yield 'a list' => [['a'], 'A JSON-LD document read as a credential is an object.'];
        yield 'an undefined property' => [['@context' => ['a' => self::EX.'a'], 'b' => 1], 'The property "b" expands to no absolute IRI in the contexts of the document.'];
        yield 'a property mapped to nothing' => [['@context' => [...$vocab, 'b' => null], 'b' => 1], 'The property "b" expands to no absolute IRI in the contexts of the document.'];
        yield 'a property whose @id is null' => [['@context' => [...$vocab, 'b' => ['@id' => null]], 'b' => 1], 'The property "b" expands to no absolute IRI in the contexts of the document.'];
        yield 'an unknown keyword' => [['@context' => $vocab, '@foo' => 1], 'The property "@foo" expands to no absolute IRI in the contexts of the document.'];
        yield 'a keyword outside the subset' => [['@context' => $vocab, '@graph' => []], 'The property "@graph" expands to no absolute IRI in the contexts of the document.'];
        yield 'a relative identifier' => [['@context' => $vocab, 'id' => 'a', 'b' => 1], 'The identifier "a" expands to no absolute IRI in the contexts of the document.'];
        yield 'a relative type' => [['@context' => [...self::ALIASES, 'b' => self::EX.'b'], 'type' => 'T'], 'The type "T" expands to no absolute IRI in the contexts of the document.'];
        yield 'a relative reference' => [['@context' => [...$vocab, 'r' => ['@type' => '@id']], 'r' => 'x y'], 'The value "x y" expands to no absolute IRI in the contexts of the document.'];
        yield 'an identifier that is not a string' => [['@context' => $vocab, 'id' => 3], 'The identifier of a JSON-LD node is a string.'];
        yield 'a type that is not a string' => [['@context' => $vocab, 'type' => [3]], 'The type of a JSON-LD node is a string or a list of strings.'];
        yield 'a nested list' => [['@context' => $vocab, 'v' => [['a']]], 'A JSON-LD value is not a list nested in a list.'];
        yield 'a JSON literal' => [['@context' => [...$vocab, 'j' => ['@type' => '@json']], 'j' => ['a' => 1]], 'A JSON literal is not processed.'];
        yield 'an IRI with a space' => [['@context' => $vocab, 'id' => 'https://example.org/a b'], 'The identifier "https://example.org/a b" expands to no absolute IRI in the contexts of the document.'];
        yield 'an unknown context' => [['@context' => 'https://example.org/context.jsonld'], 'The JSON-LD context "https://example.org/context.jsonld" is not one the verifier knows.'];
        yield 'a context of another kind' => [['@context' => [3]], 'A JSON-LD context is a URL, an object, null or a list of them.'];
        yield 'an import' => [['@context' => ['@import' => 'x']], 'A JSON-LD context with "@import" is not processed.'];
        yield 'a base' => [['@context' => ['@base' => 'https://example.org/']], 'A JSON-LD context with "@base" is not processed.'];
        yield 'a language' => [['@context' => ['@language' => 'fr']], 'A JSON-LD context with "@language" is not processed.'];
        yield 'a direction' => [['@context' => ['@direction' => 'ltr']], 'A JSON-LD context with "@direction" is not processed.'];
        yield 'a vocabulary of another kind' => [['@context' => ['@vocab' => 3]], 'The "@vocab" of a JSON-LD context is an IRI or null.'];
        yield 'a term through itself' => [['@context' => ['a' => 'a:x']], 'The JSON-LD term "a" is defined through itself.'];
        yield 'a keyword redefined' => [['@context' => ['@type' => ['@container' => '@set']]], 'A JSON-LD context does not redefine the keyword "@type".'];
        yield 'a term of another kind' => [['@context' => ['a' => 3]], 'The JSON-LD term "a" is defined by an IRI or an object.'];
        yield 'a reverse term' => [['@context' => ['a' => ['@reverse' => self::EX.'a']]], 'The JSON-LD term "a" uses "@reverse", which is not processed.'];
        yield 'an @id of another kind' => [['@context' => ['a' => ['@id' => 3]]], 'The "@id" of the JSON-LD term "a" is a string.'];
        yield 'a relative @id' => [['@context' => ['a' => ['@id' => 'b']]], 'The JSON-LD term "a" does not expand to an absolute IRI.'];
        yield 'a term without IRI' => [['@context' => ['a' => ['@type' => '@id']]], 'The JSON-LD term "a" has no IRI.'];
        yield 'a type of another kind' => [['@context' => ['a' => ['@id' => self::EX.'a', '@type' => 3]]], 'The type of the JSON-LD term "a" is not processed.'];
        yield 'a keyword type' => [['@context' => ['a' => ['@id' => self::EX.'a', '@type' => '@list']]], 'The type of the JSON-LD term "a" is not processed.'];
        yield 'a relative type IRI' => [['@context' => ['a' => ['@id' => self::EX.'a', '@type' => 'T']]], 'The type of the JSON-LD term "a" is not processed.'];
        yield 'an unknown keyword type' => [['@context' => ['a' => ['@id' => self::EX.'a', '@type' => '@foo']]], 'The type of the JSON-LD term "a" is not processed.'];
        yield 'an index container' => [['@context' => ['a' => ['@id' => self::EX.'a', '@container' => '@index']]], 'The container of the JSON-LD term "a" is not processed.'];
        yield 'a container of another kind' => [['@context' => ['a' => ['@id' => self::EX.'a', '@container' => [3]]]], 'The container of the JSON-LD term "a" is not processed.'];
        yield 'an object as a value' => [['@context' => $vocab, 'v' => new stdClass()], 'A JSON-LD document holds only JSON values.'];
    }

    /**
     * @param array<array-key, mixed> $document
     */
    #[DataProvider('refused')]
    public function testWhatIsOutsideTheSubsetIsRefused(array $document, string $reason): void
    {
        $this->expectExceptionObject(InvalidCredential::because($reason));
        $this->canonical($document);
    }

    public function testNodesNestAtMostSoDeep(): void
    {
        $node = ['v' => 'leaf'];

        for ($depth = 0; $depth < Conversion::MAX_DEPTH; ++$depth) {
            $node = ['v' => $node];
        }

        self::assertSame(Conversion::MAX_DEPTH + 1, substr_count($this->canonical(['@context' => ['@vocab' => self::EX], ...$node]), "\n"));

        $this->expectExceptionObject(InvalidCredential::because('A JSON-LD document nests nodes at most 32 deep.'));
        $this->canonical(['@context' => ['@vocab' => self::EX], 'v' => $node]);
    }

    public function testContextsLoadAtMostSoDeep(): void
    {
        $loader = new BundledContexts(['https://example.org/loop' => ['@context' => 'https://example.org/loop']]);

        $this->expectExceptionObject(InvalidCredential::because('The JSON-LD contexts load each other too deeply.'));
        new ToRdf($loader)->quads(['@context' => 'https://example.org/loop']);
    }

    public function testAContextLoadsAnother(): void
    {
        $loader = new BundledContexts(['https://example.org/one' => ['@context' => ['https://example.org/two', ['a' => self::EX.'a']]], 'https://example.org/two' => ['@context' => ['@vocab' => 'https://two.example/']]]);

        self::assertSame([['_:b0', '<https://example.org/vocab#a>', '"1"', ''], ['_:b0', '<https://two.example/b>', '"2"', '']], new ToRdf($loader)->quads(['@context' => 'https://example.org/one', 'a' => '1', 'b' => '2']));
    }

    public function testAContextDocumentWithoutContextDefinesNothing(): void
    {
        $loader = new BundledContexts(['https://example.org/empty' => []]);

        self::assertSame([], new ToRdf($loader)->quads(['@context' => 'https://example.org/empty']));
    }

    public function testADocumentYieldsAtMostSoManyQuads(): void
    {
        $conversion = new Conversion(new ContextProcessor(new BundledContexts()), 2);

        self::assertCount(2, $conversion->document(['@context' => ['@vocab' => self::EX], 'a' => ['1', '1', '2']]));

        $this->expectExceptionObject(InvalidCredential::because('A JSON-LD document yields at most 2 quads.'));
        new Conversion(new ContextProcessor(new BundledContexts()), 2)->document(['@context' => ['@vocab' => self::EX], 'a' => ['1', '2', '3']]);
    }

    public function testTheBundledContexts(): void
    {
        $contexts = new BundledContexts();

        foreach (array_keys(BundledContexts::FILES) as $url) {
            self::assertIsArray($contexts->load($url)['@context'] ?? null);
        }

        self::assertSame(['@context' => []], new BundledContexts(['https://www.w3.org/ns/credentials/v2' => ['@context' => []]])->load('https://www.w3.org/ns/credentials/v2'));
    }

    public function testTheActiveContext(): void
    {
        $context = new ActiveContext(['p' => new TermDefinition('https://p.example/', null, null, false, null, true), 'n' => null], 'https://v.example/');

        self::assertSame('@id', $context->expandIri('@id', true));
        self::assertNull($context->expandIri('@foo', true));
        self::assertNull($context->expandIri('n', true));
        self::assertSame('https://p.example/x', $context->expandIri('p:x', false));
        self::assertSame('p://x', $context->expandIri('p://x', true));
        self::assertSame('_:x', $context->expandIri('_:x', true));
        self::assertSame('https://v.example/x', $context->expandIri('x', true));
        self::assertSame('x', $context->expandIri('x', false));
        self::assertSame('p', $context->expandIri('p', false));
        self::assertSame('q:x', $context->expandIri('q:x', true));
        self::assertSame('n:x', $context->expandIri('n:x', true));
        self::assertSame('m:x', new ActiveContext(['m' => new TermDefinition(null, null, null, false, null, true)])->expandIri('m:x', true));
        self::assertSame('https://p.example/x:y', $context->expandIri('p:x:y', true));
        self::assertSame('x', new ActiveContext()->expandIri('x', true));
        self::assertNull($context->term('n'));
        self::assertNull($context->term('missing'));
    }

    public function testNumericKeysAreTerms(): void
    {
        self::assertSame("_:c14n0 <https://example.org/vocab#7> \"x\" .\n", $this->canonical(['@context' => ['@vocab' => self::EX], '7' => 'x']));
    }

    public function testAListObjectHoldingANode(): void
    {
        self::assertSame(
            "_:c14n0 <https://example.org/vocab#name> \"n\" .\n_:c14n1 <https://example.org/vocab#one> _:c14n2 .\n_:c14n2 <http://www.w3.org/1999/02/22-rdf-syntax-ns#first> _:c14n0 .\n_:c14n2 <http://www.w3.org/1999/02/22-rdf-syntax-ns#rest> <http://www.w3.org/1999/02/22-rdf-syntax-ns#nil> .\n",
            $this->canonical(['@context' => ['@vocab' => self::EX], 'one' => ['@list' => ['name' => 'n']]]),
        );
    }

    public function testEachGraphHasItsOwnName(): void
    {
        self::assertSame(
            "_:c14n1 <https://example.org/vocab#name> \"b\" _:c14n0 .\n_:c14n2 <https://example.org/vocab#name> \"a\" _:c14n4 .\n_:c14n3 <https://example.org/vocab#proof> _:c14n0 .\n_:c14n3 <https://example.org/vocab#proof> _:c14n4 .\n",
            $this->canonical(['@context' => ['@vocab' => self::EX, 'proof' => ['@container' => '@graph']], 'proof' => [['name' => 'a'], ['name' => 'b']]]),
        );
    }

    public function testGraphsNestAtMostSoDeep(): void
    {
        $node = ['name' => 'leaf'];

        for ($depth = 0; $depth < Conversion::MAX_DEPTH; ++$depth) {
            $node = ['proof' => $node];
        }

        $context = ['@vocab' => self::EX, 'proof' => ['@container' => '@graph']];
        $quads = new ToRdf(new BundledContexts())->quads(['@context' => $context, ...$node]);
        self::assertCount(Conversion::MAX_DEPTH + 1, $quads);
        self::assertCount(Conversion::MAX_DEPTH + 1, array_unique(array_column($quads, 3)));

        $this->expectExceptionObject(InvalidCredential::because('A JSON-LD document nests nodes at most 32 deep.'));
        new ToRdf(new BundledContexts())->quads(['@context' => $context, 'proof' => $node]);
    }

    public function testTypedLiteralsKeepTheirDatatype(): void
    {
        $context = ['@vocab' => self::EX, 'flag' => ['@type' => self::EX.'Flag'], 'measure' => ['@type' => self::EX.'Measure']];

        self::assertSame(
            "_:c14n0 <https://example.org/vocab#flag> \"true\"^^<https://example.org/vocab#Flag> .\n_:c14n0 <https://example.org/vocab#measure> \"2.5E0\"^^<https://example.org/vocab#Measure> .\n_:c14n0 <https://example.org/vocab#measure> \"3\"^^<https://example.org/vocab#Measure> .\n",
            $this->canonical(['@context' => $context, 'flag' => true, 'measure' => [2.5, 3]]),
        );
    }

    public function testAVocabularyMayBeATerm(): void
    {
        self::assertSame("_:c14n0 <https://v.example/name> \"x\" .\n", $this->canonical(['@context' => [['v' => 'https://v.example/'], ['@vocab' => 'v']], 'name' => 'x']));
    }

    public function testATermMayNameAnother(): void
    {
        self::assertSame("_:c14n0 <https://a.example/A> \"x\" .\n", $this->canonical(['@context' => ['A' => 'https://a.example/A', 'B' => ['@id' => 'A']], 'B' => 'x']));
        self::assertSame("_:c14n0 <a:x> \"y\" .\n", $this->canonical(['@context' => ['a' => null, 'b' => 'a:x'], 'b' => 'y']));
        self::assertSame("_:c14n1 <https://n.example/n> \"x\" _:c14n0 .\n_:c14n2 <https://g.example/g> _:c14n0 .\n", $this->canonical(['@context' => ['g' => ['@id' => 'https://g.example/g', '@container' => ['@set', '@graph']], 'n' => 'https://n.example/n'], 'g' => ['n' => 'x']]));
    }

    public function testContextsLoadUpToTheirDepth(): void
    {
        $contexts = ['https://example.org/c' => ['@context' => ['@vocab' => self::EX]]];

        // Each context loads the next: the document's loads the first at depth 1, the last at the deepest allowed.
        for ($level = 1; $level < ContextProcessor::MAX_DEPTH; ++$level) {
            $contexts['https://example.org/c'.str_repeat('+', $level)] = ['@context' => 'https://example.org/c'.str_repeat('+', $level - 1)];
        }

        $deepest = 'https://example.org/c'.str_repeat('+', ContextProcessor::MAX_DEPTH - 1);
        self::assertSame([['_:b0', '<https://example.org/vocab#a>', '"1"', '']], new ToRdf(new BundledContexts($contexts))->quads(['@context' => $deepest, 'a' => '1']));

        $contexts[$deepest.'+'] = ['@context' => $deepest];
        $this->expectExceptionObject(InvalidCredential::because('The JSON-LD contexts load each other too deeply.'));
        new ToRdf(new BundledContexts($contexts))->quads(['@context' => $deepest.'+', 'a' => '1']);
    }

    /**
     * @param array<array-key, mixed> $document
     */
    private function canonical(array $document): string
    {
        return Canonicalizer::canonicalize(new ToRdf(new BundledContexts())->quads($document));
    }
}
