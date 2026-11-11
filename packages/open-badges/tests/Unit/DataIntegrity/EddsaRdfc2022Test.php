<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\DataIntegrity;

use DateTimeImmutable;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\Encoding\Multibase;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\JsonLd\BundledContexts;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Key\RsaSigner;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadges\Tests\Unit\Key\Ed25519KeyTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The `eddsa-rdfc-2022` cryptosuite against the test vector its specification publishes, and on the credentials the
 * library builds.
 */
#[CoversClass(EddsaRdfc2022::class)]
#[CoversClass(DidKeyMethods::class)]
final class EddsaRdfc2022Test extends TestCase
{
    private const string METHOD = 'did:key:z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2#z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2';

    private const string PROOF_VALUE = 'z2YwC8z3ap7yx1nZYCg4L3j3ApHsF8kgPdSb5xoS1VR7vPG3F561B52hYnQF9iseabecm3ijx4K1FBTQsCZahKZme';

    public function testTheSpecificationsVector(): void
    {
        $suite = $this->suite();
        $document = $this->alumni();

        self::assertSame(
            "<did:example:abcdefgh> <https://www.w3.org/ns/credentials/examples#alumniOf> \"The School of Examples\" .\n"
            ."<urn:uuid:58172aac-d8ba-11ed-83dd-0b3aef56cc33> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <https://www.w3.org/2018/credentials#VerifiableCredential> .\n"
            ."<urn:uuid:58172aac-d8ba-11ed-83dd-0b3aef56cc33> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <https://www.w3.org/ns/credentials/examples#AlumniCredential> .\n"
            ."<urn:uuid:58172aac-d8ba-11ed-83dd-0b3aef56cc33> <https://schema.org/description> \"A minimum viable example of an Alumni Credential.\" .\n"
            ."<urn:uuid:58172aac-d8ba-11ed-83dd-0b3aef56cc33> <https://schema.org/name> \"Alumni Credential\" .\n"
            ."<urn:uuid:58172aac-d8ba-11ed-83dd-0b3aef56cc33> <https://www.w3.org/2018/credentials#credentialSubject> <did:example:abcdefgh> .\n"
            ."<urn:uuid:58172aac-d8ba-11ed-83dd-0b3aef56cc33> <https://www.w3.org/2018/credentials#issuer> <https://vc.example/issuers/5678> .\n"
            ."<urn:uuid:58172aac-d8ba-11ed-83dd-0b3aef56cc33> <https://www.w3.org/2018/credentials#validFrom> \"2023-01-01T00:00:00Z\"^^<http://www.w3.org/2001/XMLSchema#dateTime> .\n",
            $suite->canonicalize($document),
        );

        $secured = $suite->sign($document, $this->signer(), self::METHOD, new DateTimeImmutable('2023-02-24 18:36:38 America/Toronto'));

        self::assertSame([
            'type' => 'DataIntegrityProof',
            'cryptosuite' => 'eddsa-rdfc-2022',
            'created' => '2023-02-24T23:36:38Z',
            'verificationMethod' => self::METHOD,
            'proofPurpose' => 'assertionMethod',
            'proofValue' => self::PROOF_VALUE,
        ], $secured['proof'] ?? null);
        self::assertSame($document, $suite->verify($secured, new DidKeyMethods()));
    }

    public function testACredentialOfTheLibraryIsSignedAndVerified(): void
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $method = 'https://example.org/issuer#key-1';
        $credential = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example', 'https://example.org'), $signer)
            ->credential(new Achievement('https://example.org/badges/gold', 'Gold', 'Fifty comments.', 'Comment on fifty items.', 'https://example.org/gold.png'), Recipient::email('ada@example.org', 'saltsalt'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f', new DateTimeImmutable('2026-11-07 10:00:00 UTC'));
        $suite = new EddsaRdfc2022();
        $secured = $suite->sign($credential, $signer, $method, new DateTimeImmutable('2026-11-07 10:00:00 UTC'));

        self::assertSame($credential, $suite->verify($secured, new DidKeyMethods([$method => $signer->verificationKey()])));
        self::assertStringContainsString('<https://purl.imsglobal.org/spec/vc/ob/vocab.html#hashed> "true"^^<https://www.w3.org/2001/XMLSchema#boolean>', $suite->canonicalize($credential));

        $reordered = array_reverse($secured, true);
        self::assertSame($reordered['proof'], $secured['proof']);
        self::assertSame(array_reverse($credential, true), $suite->verify($reordered, new DidKeyMethods([$method => $signer->verificationKey()])));
    }

    /**
     * @return iterable<string, array{callable(array<string, mixed>): array<array-key, mixed>}>
     */
    public static function altered(): iterable
    {
        yield 'a changed value' => [static fn (array $secured): array => ['name' => 'Alumnus Credential'] + $secured];
        yield 'a removed property' => [static function (array $secured): array {
            unset($secured['description']);

            return $secured;
        }];
        yield 'a changed creation' => [static fn (array $secured): array => array_replace_recursive($secured, ['proof' => ['created' => '2023-02-24T23:36:39Z']])];
        yield 'a truncated signature' => [static fn (array $secured): array => array_replace_recursive($secured, ['proof' => ['proofValue' => substr(self::PROOF_VALUE, 0, -1)]])];
        yield 'another context' => [static fn (array $secured): array => ['@context' => ['https://www.w3.org/ns/credentials/v2', 'https://www.w3.org/ns/credentials/examples/v2', ['alumniOf' => 'https://example.org/other']]] + $secured];
    }

    /**
     * @param callable(array<string, mixed>): array<array-key, mixed> $alter
     */
    #[DataProvider('altered')]
    public function testAnAlteredDocumentDoesNotVerify(callable $alter): void
    {
        $suite = $this->suite();
        $secured = $suite->sign($this->alumni(), $this->signer(), self::METHOD, new DateTimeImmutable('2023-02-24T23:36:38Z'));

        $this->expectExceptionObject(InvalidCredential::because('The signature of the credential does not verify.'));
        $suite->verify($alter($secured), new DidKeyMethods());
    }

    public function testAnotherKeyDoesNotVerify(): void
    {
        $suite = $this->suite();
        $secured = $suite->sign($this->alumni(), $this->signer(), self::METHOD, new DateTimeImmutable('2023-02-24T23:36:38Z'));
        $other = Ed25519Signer::fromSeed(str_repeat("\2", 32))->verificationKey();

        $this->expectExceptionObject(InvalidCredential::because('The signature of the credential does not verify.'));
        $suite->verify($secured, new DidKeyMethods([self::METHOD => $other]));
    }

    public function testAKeyOfAnotherAlgorithmDoesNotVerify(): void
    {
        $suite = $this->suite();
        $secured = $suite->sign($this->alumni(), $this->signer(), self::METHOD, new DateTimeImmutable('2023-02-24T23:36:38Z'));
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        self::assertIsString($pem);

        $this->expectExceptionObject(InvalidCredential::because('The signature of the credential does not verify.'));
        $suite->verify($secured, new DidKeyMethods([self::METHOD => RsaSigner::fromPem($pem)->verificationKey()]));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function malformedProofs(): iterable
    {
        $proof = ['type' => 'DataIntegrityProof', 'cryptosuite' => 'eddsa-rdfc-2022', 'verificationMethod' => self::METHOD, 'proofPurpose' => 'assertionMethod', 'proofValue' => self::PROOF_VALUE];
        $kind = 'The proof is not a DataIntegrityProof of the cryptosuite eddsa-rdfc-2022.';
        $shape = 'The proof asserts with a verification method and holds its value in base 58.';

        yield 'no proof' => [[], 'A document secured with Data Integrity has one proof, an object.'];
        yield 'two proofs' => [['proof' => [$proof, $proof]], 'A document secured with Data Integrity has one proof, an object.'];
        yield 'a proof of another kind' => [['proof' => 'x'], 'A document secured with Data Integrity has one proof, an object.'];
        yield 'another type' => [['proof' => ['type' => 'Ed25519Signature2020'] + $proof], $kind];
        yield 'another cryptosuite' => [['proof' => ['cryptosuite' => 'eddsa-jcs-2022'] + $proof], $kind];
        yield 'another purpose' => [['proof' => ['proofPurpose' => 'authentication'] + $proof], $shape];
        yield 'no method' => [['proof' => ['verificationMethod' => null] + $proof], $shape];
        yield 'no value' => [['proof' => ['proofValue' => 3] + $proof], $shape];
        yield 'a value in base 64' => [['proof' => ['proofValue' => 'u'.substr(self::PROOF_VALUE, 1)] + $proof], $shape];
        yield 'an unknown method' => [['proof' => ['verificationMethod' => 'https://example.org/key'] + $proof], 'The verification method "https://example.org/key" is not one the verifier knows.'];
    }

    /**
     * @param array<array-key, mixed> $document
     */
    #[DataProvider('malformedProofs')]
    public function testAMalformedProofIsRefused(array $document, string $reason): void
    {
        $this->expectExceptionObject(InvalidCredential::because($reason));
        $this->suite()->verify($this->alumni() + $document, new DidKeyMethods());
    }

    public function testADocumentIsSignedOnce(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A document given a Data Integrity proof has its "@context" and no proof yet.'));
        $this->suite()->sign(['proof' => null] + $this->alumni(), $this->signer(), self::METHOD, new DateTimeImmutable());
    }

    public function testADocumentSignedHasItsContext(): void
    {
        $document = $this->alumni();
        unset($document['@context']);

        $this->expectExceptionObject(InvalidBadge::because('A document given a Data Integrity proof has its "@context" and no proof yet.'));
        $this->suite()->sign($document, $this->signer(), self::METHOD, new DateTimeImmutable());
    }

    public function testDidKeys(): void
    {
        $methods = new DidKeyMethods();
        $key = $this->signer()->verificationKey()->publicKey;

        foreach (['did:key:'.Ed25519KeyTest::PUBLIC, self::METHOD] as $method) {
            $found = $methods->key($method);
            self::assertInstanceOf(Ed25519Key::class, $found);
            self::assertSame($key, $found->publicKey);
        }

        $known = Ed25519Signer::fromSeed(str_repeat("\2", 32))->verificationKey();
        self::assertSame($known, new DidKeyMethods([self::METHOD => $known])->key(self::METHOD));

        $other = Ed25519Signer::fromSeed(str_repeat("\2", 32))->verificationKey()->toMultibase();

        foreach (['did:key:'.Ed25519KeyTest::PUBLIC.'#'.$other, 'did:key:z6LSbysY2xFMRpGMhb7tFTLMpeuPRaqaWM1yECx2AtzE3KCc', 'x did:key:'.Ed25519KeyTest::PUBLIC, 'did:key:'.Ed25519KeyTest::PUBLIC.'#', 'did:key:'.Ed25519KeyTest::PUBLIC."\n", 'did:web:example.org'] as $unknown) {
            try {
                $methods->key($unknown);
                self::fail(\sprintf('"%s" was expected to be refused.', $unknown));
            } catch (InvalidCredential $refused) {
                self::assertSame(\sprintf('The verification method "%s" is not one the verifier knows.', $unknown), $refused->getMessage());
            }
        }
    }

    private function suite(): EddsaRdfc2022
    {
        return new EddsaRdfc2022(new BundledContexts(['https://www.w3.org/ns/credentials/examples/v2' => ['@context' => ['@vocab' => 'https://www.w3.org/ns/credentials/examples#']]]));
    }

    private function signer(): Ed25519Signer
    {
        return Ed25519Signer::fromSeed(substr(Multibase::decode(Ed25519KeyTest::SECRET), 2));
    }

    /**
     * @return array<string, mixed>
     */
    private function alumni(): array
    {
        return [
            '@context' => ['https://www.w3.org/ns/credentials/v2', 'https://www.w3.org/ns/credentials/examples/v2'],
            'id' => 'urn:uuid:58172aac-d8ba-11ed-83dd-0b3aef56cc33',
            'type' => ['VerifiableCredential', 'AlumniCredential'],
            'name' => 'Alumni Credential',
            'description' => 'A minimum viable example of an Alumni Credential.',
            'issuer' => 'https://vc.example/issuers/5678',
            'validFrom' => '2023-01-01T00:00:00Z',
            'credentialSubject' => ['id' => 'did:example:abcdefgh', 'alumniOf' => 'The School of Examples'],
        ];
    }
}
