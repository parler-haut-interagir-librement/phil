<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Infrastructure\Console\ExportCommand;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCourses;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\CertificatesBundle\Tests\Unit\Support\Ob3Shape;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Jwt\JwtVerifier;
use Phil\OpenBadgesBundle\Application\Securing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The credentials the export writes for 1EdTech's conformance validator, checked offline for the shape Open Badges 3.0
 * describes: what can be known before the validator runs against a deployed issuer.
 */
#[CoversClass(Awarding::class)]
#[CoversClass(ExportCommand::class)]
final class ExportedShapeTest extends TestCase
{
    public function testAnExportedTokenHasTheShapeOfAnOpenBadgeCredential(): void
    {
        $exported = $this->export(Securing::JWT, 'ada@example.org');
        [$header, $claims] = JwtVerifier::peek($exported);

        self::assertSame([], Ob3Shape::problems($claims));
        self::assertSame([], Ob3Shape::tokenProblems($header, $claims));
        self::assertSame('vc+jwt', $header['typ'] ?? null);
    }

    public function testAnExportedDocumentHasTheShapeOfAnOpenBadgeCredentialWithItsProof(): void
    {
        $document = json_decode($this->export(Securing::DATA_INTEGRITY, 'did:example:ada'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);

        self::assertSame([], Ob3Shape::problems($document));
        self::assertSame([], Ob3Shape::proofProblems($document));
    }

    #[CoversNothing]
    public function testTheShapeCheckFindsWhatABrokenCredentialBreaks(): void
    {
        self::assertSame([
            '@context: the Verifiable Credentials 2.0 context first, then the Open Badges 3.0 one',
            'id: a URI',
            'type: VerifiableCredential and OpenBadgeCredential',
            'name: a string',
            'issuer: a Profile with its id',
            'validFrom: a date and time with its offset',
            'validUntil: a date and time with its offset',
            'credentialSubject: an AchievementSubject',
            'credentialStatus: a BitstringStatusListEntry, its index a string of digits',
        ], Ob3Shape::problems(['@context' => [Ob3Shape::OPEN_BADGES_CONTEXT], 'type' => ['VerifiableCredential'], 'name' => ' ', 'issuer' => 'https://example.com', 'validFrom' => '2026-11-08', 'validUntil' => 1, 'credentialStatus' => ['type' => 'BitstringStatusListEntry', 'statusPurpose' => 'revocation', 'statusListIndex' => 3, 'statusListCredential' => 'https://example.com/s']]));

        self::assertContains('credentialSubject: an id or an identifier', Ob3Shape::problems(['credentialSubject' => ['type' => 'AchievementSubject', 'identifier' => []]]));
        self::assertSame([
            'credentialSubject.identifier: a hashed identity is sha256$ and 64 hexadecimal digits, with its salt',
            'credentialSubject.identifier: IdentityObjects with hashed, identityHash and identityType',
            'credentialSubject.achievement.criteria: an id or a narrative',
            'credentialSubject.achievement.image: an Image with its id',
        ], \array_slice(Ob3Shape::problems(['credentialSubject' => [
            'type' => ['AchievementSubject'],
            'identifier' => [['type' => 'IdentityObject', 'hashed' => true, 'identityHash' => 'md5$abc', 'identityType' => 'emailAddress'], 'nope'],
            'achievement' => ['id' => 'https://example.com/a', 'type' => 'Achievement', 'name' => 'A', 'description' => '', 'criteria' => [], 'image' => 'https://example.com/i.png'],
        ]]), -4));
        self::assertContains('credentialSubject.achievement: an Achievement with its id, name and description', Ob3Shape::problems(['credentialSubject' => ['type' => 'AchievementSubject', 'id' => 'did:example:a', 'achievement' => ['id' => 'nope']]]));

        self::assertSame(['alg: EdDSA or RS256', 'kid or jwk: the key that verifies it', 'iss: the issuer of the credential', 'jti: the id of the credential', 'nbf: the validFrom of the credential', 'exp: the validUntil of the credential'], Ob3Shape::tokenProblems(['alg' => 'none'], ['iss' => 'x', 'validUntil' => '2027-01-01T00:00:00Z']));
        self::assertSame(['proof: a DataIntegrityProof of an EdDSA cryptosuite'], Ob3Shape::proofProblems(['proof' => ['type' => 'DataIntegrityProof', 'cryptosuite' => 'ecdsa-rdfc-2019']]));
        self::assertSame(['proof.proofPurpose: assertionMethod', 'proof.verificationMethod: a key of the issuer', 'proof.created: a date and time with its offset', 'proof.proofValue: base 58, starting with "z"'], Ob3Shape::proofProblems(['issuer' => ['id' => 'https://example.com/i'], 'proof' => ['type' => 'DataIntegrityProof', 'cryptosuite' => 'eddsa-rdfc-2022', 'proofPurpose' => 'authentication', 'verificationMethod' => 'https://other.example/i#key-1', 'proofValue' => 'u0O']]));
    }

    private function export(string $securing, string $identity): string
    {
        $clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $courses = new InMemoryCourses();
        $courses->save(Kit::firstAid());

        $certificates = new InMemoryCertificates();
        $certificate = new Awarding(new Courses($courses), $certificates, new CredentialIssuer(Kit::issuer(), Kit::signer(), Kit::METHOD), new Securing(Kit::signer(), Kit::METHOD, $securing, $clock), new FixedUrls(), $clock, new StatusListLayout('certificates', 8))
            ->award('first-aid', 'Ada Lovelace', $identity, new Attainment(90));
        self::assertInstanceOf(Certificate::class, $certificate);

        $tester = new CommandTester(new ExportCommand($certificates));
        $tester->execute(['code' => $certificate->code->value]);

        return $tester->getDisplay();
    }
}
