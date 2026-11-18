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

/**
 * The protected terms of JSON-LD 1.1: the contexts of verifiable credentials protect theirs, and a later context may
 * define one again only the same, unless it is scoped to a property.
 */
#[CoversClass(ContextProcessor::class)]
#[CoversClass(Conversion::class)]
#[CoversClass(TermDefinition::class)]
#[CoversClass(TermDefining::class)]
#[CoversClass(ActiveContext::class)]
#[CoversClass(ToRdf::class)]
final class ProtectedTermsTest extends TestCase
{
    private const string VC = 'https://www.w3.org/ns/credentials/v2';

    private const string EX = 'https://example.org/vocab#';

    /**
     * @return iterable<string, array{string}>
     */
    public static function termsOfTheCredentialsContext(): iterable
    {
        yield 'id' => ['id'];
        yield 'type' => ['type'];
        yield 'name' => ['name'];
        yield 'description' => ['description'];
    }

    #[DataProvider('termsOfTheCredentialsContext')]
    public function testATermOfTheCredentialsContextIsNotRedefined(string $term): void
    {
        $this->expectExceptionObject(InvalidCredential::because(\sprintf('The JSON-LD term "%s" is protected: a context does not redefine it.', $term)));
        $this->canonical(['@context' => [self::VC, [$term => 'https://attacker.example/'.$term]], 'name' => 'x']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function termsOfACredential(): iterable
    {
        yield 'issuer' => ['issuer'];
        yield 'credentialSubject' => ['credentialSubject'];
        yield 'validFrom' => ['validFrom'];
        yield 'validUntil' => ['validUntil'];
        yield 'id' => ['id'];
    }

    /**
     * The terms of a credential are scoped to its type `VerifiableCredential`: a type applied after it — types apply in
     * lexicographic order — does not redefine them.
     */
    #[DataProvider('termsOfACredential')]
    public function testATypeAppliedAfterTheCredentialDoesNotRedefineItsTerms(string $term): void
    {
        $evil = ['ZEvil' => ['@id' => 'https://attacker.example/ZEvil', '@context' => [$term => 'https://attacker.example/'.$term]]];

        $this->expectExceptionObject(InvalidCredential::because(\sprintf('The JSON-LD term "%s" is protected: a context does not redefine it.', $term)));
        $this->canonical(['@context' => [self::VC, $evil], 'type' => ['VerifiableCredential', 'ZEvil'], 'issuer' => 'https://example.org/issuer']);
    }

    public function testAnIssuerDefinedBeforeTheTypeOfTheCredentialKeepsItsMeaningInTheCredential(): void
    {
        $canonical = $this->canonical(['@context' => [self::VC, ['issuer' => 'https://attacker.example/issuer']], 'type' => 'VerifiableCredential', 'issuer' => 'https://example.org/issuer']);

        self::assertStringContainsString('<https://www.w3.org/2018/credentials#issuer> <https://example.org/issuer>', $canonical);
        self::assertStringNotContainsString('attacker', $canonical);
    }

    public function testAnEmbeddedContextDoesNotRedefineAProtectedTermOfANestedNode(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The JSON-LD term "name" is protected: a context does not redefine it.'));
        $this->canonical(['@context' => [self::VC], 'type' => 'VerifiableCredential', 'credentialSubject' => ['@context' => ['name' => 'https://attacker.example/name'], 'name' => 'x']]);
    }

    public function testATypeScopedContextDoesNotRedefineAProtectedTerm(): void
    {
        $context = ['@vocab' => self::EX, 'label' => ['@id' => self::EX.'label', '@protected' => true], 'Thing' => ['@id' => self::EX.'Thing', '@context' => ['label' => 'https://other.example/label']]];

        $this->expectExceptionObject(InvalidCredential::because('The JSON-LD term "label" is protected: a context does not redefine it.'));
        $this->canonical(['@context' => $context, '@type' => 'Thing', 'label' => 'x']);
    }

    public function testTheCredentialsContextMayBeRepeated(): void
    {
        $once = $this->canonical(['@context' => [self::VC], 'type' => 'VerifiableCredential', 'issuer' => 'https://example.org/issuer']);

        self::assertSame($once, $this->canonical(['@context' => [self::VC, self::VC], 'type' => 'VerifiableCredential', 'issuer' => 'https://example.org/issuer']));
        self::assertStringContainsString('<https://www.w3.org/2018/credentials#issuer> <https://example.org/issuer>', $once);
    }

    public function testATermDefinedAgainTheSameStaysProtected(): void
    {
        $protected = ['label' => ['@id' => self::EX.'label', '@protected' => true]];

        $this->expectExceptionObject(InvalidCredential::because('The JSON-LD term "label" is protected: a context does not redefine it.'));
        $this->canonical(['@context' => [$protected, ['label' => self::EX.'label'], ['label' => 'https://other.example/label']], 'label' => 'x']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>|string}>
     */
    public static function redefinitions(): iterable
    {
        yield 'another IRI' => [['@id' => self::EX.'p'], ['@id' => self::EX.'q']];
        yield 'a type' => [['@id' => self::EX.'p'], ['@id' => self::EX.'p', '@type' => '@id']];
        yield 'a container' => [['@id' => self::EX.'p'], ['@id' => self::EX.'p', '@container' => '@list']];
        yield 'a scoped context of null' => [['@id' => self::EX.'p'], ['@id' => self::EX.'p', '@context' => null]];
        yield 'another scoped context' => [['@id' => self::EX.'p', '@context' => ['a' => self::EX.'a']], ['@id' => self::EX.'p', '@context' => ['a' => self::EX.'b']]];
        yield 'a prefix' => [['@id' => 'https://p.example/'], 'https://p.example/'];
        yield 'a mapping to nothing' => [['@id' => self::EX.'p'], ['@id' => null]];
    }

    /**
     * @param array<string, mixed>        $first
     * @param array<string, mixed>|string $second
     */
    #[DataProvider('redefinitions')]
    public function testAProtectedTermIsRedefinedOnlyTheSame(array $first, array|string $second): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The JSON-LD term "p" is protected: a context does not redefine it.'));
        $this->canonical(['@context' => [['@protected' => true, 'p' => $first], ['p' => $second]], 'p' => 'x']);
    }

    public function testATermMappedToNothingIsProtectedToo(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The JSON-LD term "p" is protected: a context does not redefine it.'));
        $this->canonical(['@context' => [['@protected' => true, 'p' => null], ['p' => self::EX.'p']], 'p' => 'x']);
    }

    public function testATermMayLeaveTheProtectionOfItsContext(): void
    {
        self::assertSame(
            "_:c14n0 <https://other.example/p> \"x\" .\n",
            $this->canonical(['@context' => [['@protected' => true, 'p' => ['@id' => self::EX.'p', '@protected' => false]], ['p' => 'https://other.example/p']], 'p' => 'x']),
        );
    }

    public function testAnUnprotectedTermIsRedefined(): void
    {
        self::assertSame(
            "_:c14n0 <https://other.example/p> \"x\" .\n",
            $this->canonical(['@context' => [['@protected' => false, 'p' => self::EX.'p'], ['p' => 'https://other.example/p']], 'p' => 'x']),
        );
    }

    public function testAPropertyScopedContextRedefinesAProtectedTerm(): void
    {
        $context = ['@protected' => true, 'label' => self::EX.'label', 'child' => ['@id' => self::EX.'child', '@context' => ['label' => 'https://scoped.example/label']]];

        self::assertSame(
            "_:c14n0 <https://scoped.example/label> \"inner\" .\n_:c14n1 <https://example.org/vocab#child> _:c14n0 .\n_:c14n1 <https://example.org/vocab#label> \"outer\" .\n",
            $this->canonical(['@context' => $context, 'label' => 'outer', 'child' => ['label' => 'inner']]),
        );
    }

    public function testAPropertyScopedContextOfAScalarRedefinesAProtectedTerm(): void
    {
        $context = ['@protected' => true, 'assert' => self::EX.'assert', 'purpose' => ['@id' => self::EX.'purpose', '@type' => '@vocab', '@context' => ['assert' => 'https://scoped.example/assert']]];

        self::assertSame(
            "_:c14n0 <https://example.org/vocab#purpose> <https://scoped.example/assert> .\n",
            $this->canonical(['@context' => $context, 'purpose' => 'assert']),
        );
    }

    public function testAContextDoesNotClearProtectedTerms(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('A JSON-LD context does not clear protected terms.'));
        $this->canonical(['@context' => [self::VC, null], 'name' => 'x']);
    }

    public function testAContextClearsTermsThatAreNotProtected(): void
    {
        self::assertSame("_:c14n0 <https://other.example/name> \"x\" .\n", $this->canonical(['@context' => [['@protected' => false, 'name' => self::EX.'name'], null, ['@vocab' => 'https://other.example/']], 'name' => 'x']));
    }

    public function testAPropertyScopedContextClearsProtectedTerms(): void
    {
        $context = ['@protected' => true, 'child' => ['@id' => self::EX.'child', '@context' => null]];

        self::assertSame(
            "_:c14n0 <https://example.org/vocab#child> <https://example.org/c> .\n",
            $this->canonical(['@context' => $context, 'child' => ['@id' => 'https://example.org/c']]),
        );
    }

    public function testTheProtectionOfAContextIsTrueOrFalse(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The "@protected" of a JSON-LD context is true or false.'));
        $this->canonical(['@context' => ['@protected' => 'yes', 'p' => self::EX.'p'], 'p' => 'x']);
    }

    public function testTheProtectionOfATermIsTrueOrFalse(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The "@protected" of the JSON-LD term "p" is true or false.'));
        $this->canonical(['@context' => ['p' => ['@id' => self::EX.'p', '@protected' => 1]], 'p' => 'x']);
    }

    public function testTheDefinitionsRecordTheirProtection(): void
    {
        $active = new ContextProcessor(new BundledContexts())->process(new ActiveContext(), [['@protected' => true, 'p' => self::EX.'p', 'q' => ['@id' => self::EX.'q', '@protected' => false], 'n' => null], ['r' => self::EX.'r']]);

        self::assertTrue($active->term('p')?->protected);
        self::assertFalse($active->term('q')?->protected);
        self::assertTrue($active->term('n')?->protected);
        self::assertNull($active->term('n')->iri);
        self::assertFalse($active->term('r')?->protected);
        self::assertFalse(new TermDefinition(self::EX.'p', null, null, false, null, false)->protected);
        self::assertTrue(new TermDefinition(self::EX.'p', null, null, false, null, false)->sameAs(new TermDefinition(self::EX.'p', null, null, false, null, false, true)));
    }

    /**
     * @param array<array-key, mixed> $document
     */
    private function canonical(array $document): string
    {
        return Canonicalizer::canonicalize(new ToRdf(new BundledContexts())->quads($document));
    }
}
