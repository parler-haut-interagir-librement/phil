<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Unit;

use JsonException;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Jwt\JwtVerifier;
use Phil\OpenBadges\Key\RsaSigner;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadgesBundle\Application\SecuredCredential;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Application\StatusListContent;
use Phil\OpenBadgesBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\OpenBadgesBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Securing::class)]
#[CoversClass(StatusListContent::class)]
#[CoversClass(SecuredCredential::class)]
final class ApplicationTest extends TestCase
{
    private const array CREDENTIAL = ['@context' => ['https://www.w3.org/ns/credentials/v2'], 'type' => ['VerifiableCredential'], 'issuer' => Kit::ISSUER, 'credentialSubject' => ['id' => 'did:example:ada']];

    public function testTokens(): void
    {
        $securing = new Securing(Kit::signer(), Kit::METHOD, Securing::JWT, new FrozenClock());
        $secured = $securing->secure(self::CREDENTIAL);
        $token = $secured->content;

        self::assertSame('application/vc+jwt', $secured->mediaType);
        self::assertTrue($secured->isToken());
        self::assertSame('credential.jwt', $secured->filename());
        self::assertSame(Kit::METHOD, JwtVerifier::peek($token)[0]['kid'] ?? null);
        self::assertSame('jwt', Kit::verifier()->verify($token)->securedAs);
    }

    public function testDataIntegrityProofs(): void
    {
        $securing = new Securing(Kit::signer(), Kit::METHOD, Securing::DATA_INTEGRITY, new FrozenClock());
        $secured = $securing->secure(self::CREDENTIAL);
        $document = json_decode($secured->content, true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($document);
        self::assertSame('application/vc', $secured->mediaType);
        self::assertFalse($secured->isToken());
        self::assertSame('credential.json', $secured->filename());
        self::assertStringNotContainsString('\\/', $secured->content);
        $proof = $document['proof'] ?? null;
        self::assertIsArray($proof);
        self::assertSame('2026-11-08T10:00:00Z', $proof['created'] ?? null);
        self::assertSame(Kit::METHOD, $proof['verificationMethod'] ?? null);
        self::assertSame(self::CREDENTIAL, Kit::verifier()->verify((string) json_encode($document))->credential);
    }

    public function testAnRsaKeySecuresWithTokens(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        self::assertIsString($pem);
        $signer = RsaSigner::fromPem($pem);
        $securing = new Securing($signer, Kit::METHOD, Securing::DATA_INTEGRITY, new FrozenClock());
        $secured = $securing->secure(self::CREDENTIAL);

        self::assertSame('application/vc+jwt', $secured->mediaType);
        self::assertSame('jwt', new CredentialVerifier(new DidKeyMethods([Kit::METHOD => $signer->verificationKey()]))->verify($secured->content)->securedAs);
    }

    public function testAStatusListContent(): void
    {
        self::assertSame('revocation', new StatusListContent([1])->purpose);
        self::assertSame([1, 2], new StatusListContent([1, 2], 'suspension')->set);

        $this->expectExceptionObject(InvalidBadge::because('The purpose of a status is "revocation" or "suspension"; "refresh" is not.'));
        new StatusListContent([], 'refresh');
    }

    public function testASecuredDocumentKeepsItsLettersAndSlashesUnescaped(): void
    {
        $secured = SecuredCredential::document(['id' => 'https://example.com/é', 'proof' => ['type' => 'DataIntegrityProof']]);

        self::assertSame('{"id":"https://example.com/é","proof":{"type":"DataIntegrityProof"}}', $secured->content);
        self::assertSame(SecuredCredential::DOCUMENT, $secured->mediaType);
    }

    public function testADocumentThatIsNoJsonIsRefused(): void
    {
        $this->expectException(JsonException::class);
        SecuredCredential::document(['proof' => [], 'name' => "\xB1"]);
    }

    public function testADocumentWithoutAnyProofIsNoSecuredCredential(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A secured credential document holds its "proof".'));
        SecuredCredential::document(['id' => 'urn:uuid:1']);
    }

    public function testADocumentWithoutItsProofIsNoSecuredCredential(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A secured credential document holds its "proof".'));
        SecuredCredential::document(['id' => 'urn:uuid:1', 'proof' => 'none']);
    }

    public function testATokenIsThreeBase64UrlParts(): void
    {
        self::assertSame('a-_.b.c', SecuredCredential::token('a-_.b.c')->content);

        foreach (['a.b', "a.b.c\n", 'a.b.c.d', ' a.b.c', 'a.b+.c', '.b.c', 'a..c', 'a.b.', ''] as $token) {
            try {
                SecuredCredential::token($token);
                self::fail(\sprintf('"%s" is no token.', $token));
            } catch (InvalidBadge $invalidBadge) {
                self::assertSame('A secured credential token is three base64url parts joined by dots.', $invalidBadge->getMessage());
            }
        }
    }
}
