<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Verification;

use DateTimeImmutable;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadges\Status\StatusListEntry;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadges\Verification\IssuerMethods;
use Phil\OpenBadges\Verification\VerifiedCredential;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CredentialVerifier::class)]
#[CoversClass(VerifiedCredential::class)]
#[CoversClass(IssuerMethods::class)]
final class CredentialVerifierTest extends TestCase
{
    private const string METHOD = 'https://example.org/issuer#key-1';

    private const string ID = 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f';

    public function testATokenIsVerifiedThroughItsKid(): void
    {
        $verified = $this->verifier()->verify(' '.$this->issuer()->issue($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 UTC'), status: new StatusListEntry('https://example.org/status/1', 3))."\n", new DateTimeImmutable('2026-11-08 UTC'));

        self::assertSame('jwt', $verified->securedAs);
        self::assertSame('https://example.org/issuer', $verified->issuer());
        self::assertSame(3, $verified->status()?->index);
        self::assertSame(['@context', 'id', 'type', 'issuer', 'validFrom', 'name', 'credentialSubject', 'credentialStatus'], array_keys($verified->credential));
    }

    public function testATokenWithoutKidUsesTheDefaultMethod(): void
    {
        $token = new JwtSigner($this->signer())->sign(['id' => self::ID, 'issuer' => 'https://example.org/issuer']);

        self::assertSame('https://example.org/issuer', new CredentialVerifier($this->methods(), self::METHOD)->verify($token)->issuer());

        $this->expectExceptionObject(InvalidCredential::because('The token names no key, and the verifier has no default one.'));
        $this->verifier()->verify($token);
    }

    public function testATokenIsCheckedAtTheMoment(): void
    {
        $token = $this->issuer()->issue($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 UTC'));

        $this->expectExceptionObject(InvalidCredential::because('The credential is not valid yet.'));
        $this->verifier()->verify($token, new DateTimeImmutable('2026-11-06 UTC'));
    }

    public function testADocumentIsVerifiedThroughItsProof(): void
    {
        $secured = $this->secured(new DateTimeImmutable('2026-11-07 UTC'), new DateTimeImmutable('2027-11-07 UTC'));
        $verified = $this->verifier()->verify((string) json_encode($secured), new DateTimeImmutable('2027-11-06 23:59:59 UTC'));

        self::assertSame('data-integrity', $verified->securedAs);
        unset($secured['proof']);
        self::assertSame($secured, $verified->credential);
        self::assertNull($verified->status());
        self::assertSame($verified->credential, $this->verifier()->verify((string) json_encode($this->secured(new DateTimeImmutable('2026-11-07 UTC'), new DateTimeImmutable('2027-11-07 UTC'))), new DateTimeImmutable('2027-01-01 UTC'))->credential);
        self::assertSame('data-integrity', $this->verifier()->verify((string) json_encode($this->secured(new DateTimeImmutable('2026-11-07 UTC'))), new DateTimeImmutable('2126-11-07 UTC'))->securedAs);
        self::assertSame('data-integrity', $this->verifier()->verify((string) json_encode($this->secured(new DateTimeImmutable('2026-11-07 UTC'))), new DateTimeImmutable('2026-11-07 UTC'))->securedAs);
    }

    public function testADocumentIsNotValidBeforeItsStart(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The credential is not valid yet.'));
        $this->verifier()->verify((string) json_encode($this->secured(new DateTimeImmutable('2026-11-07 UTC'))), new DateTimeImmutable('2026-11-06 23:59:59 UTC'));
    }

    public function testADocumentExpires(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The credential has expired.'));
        $this->verifier()->verify((string) json_encode($this->secured(new DateTimeImmutable('2026-11-07 UTC'), new DateTimeImmutable('2027-11-07 UTC'))), new DateTimeImmutable('2027-11-07 UTC'));
    }

    public function testAPeriodIsMadeOfMoments(): void
    {
        $suite = new EddsaRdfc2022();
        $context = ['https://www.w3.org/ns/credentials/v2'];

        foreach ([['validFrom' => '2026-11-07T00:00:00.123Z'], ['validUntil' => '2027-11-07T00:00:00.123+02:00']] as $period) {
            $document = $suite->sign(['@context' => $context, 'type' => 'VerifiableCredential', 'issuer' => 'https://example.org/issuer', ...$period], $this->signer(), self::METHOD, new DateTimeImmutable());
            self::assertSame($period, array_intersect_key($this->verifier()->verify((string) json_encode($document), new DateTimeImmutable('2027-01-01 UTC'))->credential, $period));
        }

        foreach ([['validFrom' => '2026-11-07'], ['validUntil' => 3]] as $period) {
            $document = $suite->sign(['@context' => $context, 'type' => 'VerifiableCredential', 'issuer' => 'https://example.org/issuer', 'description' => 'x', ...$period], $this->signer(), self::METHOD, new DateTimeImmutable());

            try {
                $this->verifier()->verify((string) json_encode($document), new DateTimeImmutable());
                self::fail('The period was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame('The period of validity of the credential is made of RFC 3339 moments.', $refused->getMessage());
            }
        }
    }

    public function testWhatIsNeitherATokenNorADocumentIsRefused(): void
    {
        foreach (['hello', '"text"', '{"a":'] as $text) {
            try {
                $this->verifier()->verify($text);
                self::fail(\sprintf('"%s" was expected to be refused.', $text));
            } catch (InvalidCredential $refused) {
                self::assertSame('A credential is a compact JSON Web Token or the JSON of a document with its proof.', $refused->getMessage());
            }
        }
    }

    public function testADocumentIsReadUpToItsDepthAndLength(): void
    {
        foreach (['{"a":'.str_repeat('[', 62).str_repeat(']', 62).'}', '{"a":"'.str_repeat('a', 1_048_576 - 8).'"}'] as $document) {
            try {
                $this->verifier()->verify($document);
                self::fail('The document was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame('A document secured with Data Integrity has one proof, an object.', $refused->getMessage());
            }
        }

        try {
            $this->verifier()->verify('{"a":'.str_repeat('[', 63).str_repeat(']', 63).'}');
            self::fail('The document was expected to be refused.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame('A credential is a compact JSON Web Token or the JSON of a document with its proof.', $invalidCredential->getMessage());
        }
    }

    public function testADocumentIsReadWithinItsLength(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('A credential is a compact JSON Web Token or the JSON of a document with its proof.'));
        $this->verifier()->verify('{"a":"'.str_repeat('a', 1_048_576).'"}');
    }

    public function testAVerifiedCredential(): void
    {
        self::assertNull(new VerifiedCredential([], 'jwt')->issuer());
        self::assertNull(new VerifiedCredential(['issuer' => ['name' => 'x']], 'jwt')->issuer());
        self::assertSame('https://example.org/issuer', new VerifiedCredential(['issuer' => ['id' => 'https://example.org/issuer']], 'jwt')->issuer());
        self::assertNull(new VerifiedCredential(['issuer' => 3], 'jwt')->issuer());

        $this->expectExceptionObject(InvalidCredential::because('The status of the credential is not a BitstringStatusListEntry.'));
        new VerifiedCredential(['credentialStatus' => 'x'], 'jwt')->status();
    }

    public function testWithoutAMomentTheCurrentTimeIsUsed(): void
    {
        foreach ([(string) json_encode($this->secured(new DateTimeImmutable('2020-01-01 UTC'), new DateTimeImmutable('2021-01-01 UTC'))), $this->issuer()->issue($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2020-01-01 UTC'), new DateTimeImmutable('2021-01-01 UTC'))] as $expired) {
            try {
                $this->verifier()->verify($expired);
                self::fail('The expired credential was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame('The credential has expired.', $refused->getMessage());
            }
        }

        foreach ([(string) json_encode($this->secured(new DateTimeImmutable('2126-01-01 UTC'))), $this->issuer()->issue($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2126-01-01 UTC'))] as $future) {
            try {
                $this->verifier()->verify($future);
                self::fail('The credential not valid yet was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame('The credential is not valid yet.', $refused->getMessage());
            }
        }
    }

    public function testADidKeyCannotSignForAnotherIssuer(): void
    {
        $forger = Ed25519Signer::fromSeed(str_repeat("\2", 32));
        $did = 'did:key:'.$forger->verificationKey()->toMultibase();
        $credential = $this->issuer()->credential($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 UTC'));

        foreach ([$did, $did.'#'.$forger->verificationKey()->toMultibase()] as $method) {
            $this->assertRefused(
                \sprintf('The verification method "%s" is not one of the issuer "https://example.org/issuer".', $method),
                (string) json_encode(new EddsaRdfc2022()->sign($credential, $forger, $method, new DateTimeImmutable('2026-11-07 UTC'))),
            );
            $this->assertRefused(
                \sprintf('The verification method "%s" is not one of the issuer "https://example.org/issuer".', $method),
                new JwtSigner($forger, $method)->sign($credential),
            );
        }
    }

    public function testAPartnerCannotIssueInAnotherName(): void
    {
        $partner = Ed25519Signer::fromSeed(str_repeat("\3", 32));
        $methods = new DidKeyMethods([self::METHOD => $this->signer()->verificationKey(), 'https://partner.example/issuer#key-1' => $partner->verificationKey(), 'https://example.org/issuer-2#key-1' => $partner->verificationKey()]);
        $credential = $this->issuer()->credential($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 UTC'));

        foreach (['https://partner.example/issuer#key-1', 'https://example.org/issuer-2#key-1'] as $method) {
            $document = (string) json_encode(new EddsaRdfc2022()->sign($credential, $partner, $method, new DateTimeImmutable('2026-11-07 UTC')));
            $token = new JwtSigner($partner, $method)->sign($credential);

            try {
                new CredentialVerifier($methods)->verify($document, new DateTimeImmutable('2026-11-08 UTC'));
                self::fail('The credential was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame(\sprintf('The verification method "%s" is not one of the issuer "https://example.org/issuer".', $method), $refused->getMessage());
            }

            $controlled = new CredentialVerifier($methods, controllers: ['https://example.org/issuer' => [$method], 'https://other.example' => []]);
            self::assertSame('https://example.org/issuer', $controlled->verify($document, new DateTimeImmutable('2026-11-08 UTC'))->issuer());
            self::assertSame('https://example.org/issuer', $controlled->verify($token, new DateTimeImmutable('2026-11-08 UTC'))->issuer());
        }
    }

    public function testADidKeyIssuesInItsOwnName(): void
    {
        $holder = Ed25519Signer::fromSeed(str_repeat("\4", 32));
        $did = 'did:key:'.$holder->verificationKey()->toMultibase();
        $credential = $this->issuer()->credential($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 UTC'));
        $credential['issuer'] = ['id' => $did, 'type' => ['Profile'], 'name' => 'Self'];

        foreach ([$did, $did.'#'.$holder->verificationKey()->toMultibase()] as $method) {
            self::assertSame($did, $this->verifier()->verify((string) json_encode(new EddsaRdfc2022()->sign($credential, $holder, $method, new DateTimeImmutable('2026-11-07 UTC'))), new DateTimeImmutable('2026-11-08 UTC'))->issuer());
            self::assertSame($did, $this->verifier()->verify(new JwtSigner($holder, $method)->sign($credential), new DateTimeImmutable('2026-11-08 UTC'))->issuer());
        }
    }

    public function testACredentialNamesItsIssuer(): void
    {
        $document = new EddsaRdfc2022()->sign(['@context' => ['https://www.w3.org/ns/credentials/v2'], 'type' => 'VerifiableCredential', 'description' => 'x'], $this->signer(), self::METHOD, new DateTimeImmutable('2026-11-07 UTC'));

        $this->assertRefused('The credential names no issuer.', (string) json_encode($document));
        $this->assertRefused('The credential names no issuer.', $this->token(['description' => 'x']));
    }

    public function testATokenIsIssuedByTheCredentialsIssuer(): void
    {
        $this->assertRefused('The "iss" claim of the token is not the issuer of the credential.', $this->token(['issuer' => 'https://example.org/issuer']));
        $this->assertRefused('The "iss" claim of the token is not the issuer of the credential.', $this->token(['issuer' => 'https://example.org/issuer', 'iss' => 'https://partner.example/issuer']));
        self::assertSame('https://example.org/issuer', $this->verifier()->verify($this->token(['issuer' => ['id' => 'https://example.org/issuer'], 'iss' => 'https://example.org/issuer']), new DateTimeImmutable('2026-11-08 UTC'))->issuer());
    }

    private function assertRefused(string $message, string $credential): void
    {
        try {
            $this->verifier()->verify($credential, new DateTimeImmutable('2026-11-08 UTC'));
            self::fail(\sprintf('The credential was expected to be refused with "%s".', $message));
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame($message, $invalidCredential->getMessage());
        }
    }

    /**
     * A token signed by the example issuer's key over the claims given, as they are.
     *
     * @param array<string, mixed> $claims
     */
    private function token(array $claims): string
    {
        $signed = Base64Url::encode((string) json_encode(['alg' => 'EdDSA', 'typ' => 'vc+jwt', 'kid' => self::METHOD])).'.'.Base64Url::encode((string) json_encode($claims));

        return $signed.'.'.Base64Url::encode($this->signer()->sign($signed));
    }

    private function verifier(): CredentialVerifier
    {
        return new CredentialVerifier($this->methods());
    }

    private function methods(): DidKeyMethods
    {
        return new DidKeyMethods([self::METHOD => $this->signer()->verificationKey()]);
    }

    private function signer(): Ed25519Signer
    {
        return Ed25519Signer::fromSeed(str_repeat("\1", 32));
    }

    private function issuer(): CredentialIssuer
    {
        return new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $this->signer(), self::METHOD);
    }

    private function achievement(): Achievement
    {
        return new Achievement('https://example.org/badges/gold', 'Gold', 'Fifty comments.', 'Comment on fifty items.');
    }

    /**
     * @return array<string, mixed>
     */
    private function secured(DateTimeImmutable $validFrom, ?DateTimeImmutable $validUntil = null): array
    {
        return new EddsaRdfc2022()->sign($this->issuer()->credential($this->achievement(), Recipient::did('did:example:ada'), self::ID, $validFrom, $validUntil), $this->signer(), self::METHOD, $validFrom);
    }
}
