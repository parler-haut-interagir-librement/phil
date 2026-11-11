<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit;

use DateTimeImmutable;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Context;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Credentials;
use Phil\OpenBadges\EndorsementIssuer;
use Phil\OpenBadges\Endorsements;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadges\Status\StatusListEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Issuer::class)]
#[CoversClass(Achievement::class)]
#[CoversClass(Recipient::class)]
#[CoversClass(Endorsements::class)]
#[CoversClass(Credentials::class)]
#[CoversClass(CredentialIssuer::class)]
#[CoversClass(EndorsementIssuer::class)]
final class ModelTest extends TestCase
{
    private const string ID = 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f';

    private const string JWT = 'aaa.bbb.ccc';

    public function testAnIssuerHasAnImageAndEndorsements(): void
    {
        $endorsement = $this->endorsement();
        $issuer = new Issuer('https://example.org/issuer', 'Example', image: 'https://example.org/logo.png', endorsements: [$endorsement, self::JWT]);

        self::assertSame([
            'id' => 'https://example.org/issuer',
            'type' => ['Profile'],
            'name' => 'Example',
            'image' => ['id' => 'https://example.org/logo.png', 'type' => 'Image'],
            'endorsement' => [$endorsement],
            'endorsementJwt' => [self::JWT],
        ], $issuer->toArray());
    }

    public function testAnIssuersImageIsAnUrl(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The identifier of the issuer\'s image is an absolute HTTP URL; "logo.png" is not.'));
        new Issuer('https://example.org/issuer', 'Example', image: 'logo.png');
    }

    public function testAnIssuersEmailIsAnAddress(): void
    {
        self::assertSame('a@b', new Issuer('https://example.org/issuer', 'Example', email: 'a@b')->email);

        foreach (['ada', 'a b@example.org', '@example.org', 'ada@', "ada@example.org\n", 'x<ada@example.org'] as $email) {
            try {
                new Issuer('https://example.org/issuer', 'Example', email: $email);
                self::fail(\sprintf('"%s" was expected to be refused.', $email));
            } catch (InvalidBadge $refused) {
                self::assertSame(\sprintf('"%s" is not an e-mail address.', $email), $refused->getMessage());
            }
        }
    }

    public function testAnIssuersEndorsementsAreChecked(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('An endorsement given as a string is a compact JSON Web Token.'));
        new Issuer('https://example.org/issuer', 'Example', endorsements: ['aaa.bbb']);
    }

    public function testAnAchievementsEndorsementsAreChecked(): void
    {
        self::assertSame([self::JWT], new Achievement('https://example.org/gold', 'Gold', '', 'Comment.', endorsements: [self::JWT])->toArray()['endorsementJwt'] ?? null);
        $this->expectExceptionObject(InvalidBadge::because('An endorsement given as a document is a signed EndorsementCredential, with its proof.'));
        new Achievement('https://example.org/gold', 'Gold', '', 'Comment.', endorsements: [['type' => ['EndorsementCredential']]]);
    }

    public function testAUrlHoldsNoCharacterAnIriRefuses(): void
    {
        foreach (['https://example.org/a<b', 'https://example.org/"', 'https://example.org/{', 'https://example.org/|', 'https://example.org/\\', 'https://example.org/^', 'https://example.org/`', 'https://exa<mple.org/', 'ftp://example.org/'] as $url) {
            try {
                Achievement::checkUrl($url, 'test');
                self::fail(\sprintf('"%s" was expected to be refused.', $url));
            } catch (InvalidBadge $refused) {
                self::assertStringContainsString($url, $refused->getMessage());
            }
        }

        Achievement::checkUrl('https://example.org/a?b=c#d', 'test');
        self::assertSame('https://example.org/a?b=c#d', new Issuer('https://example.org/a?b=c#d', 'Example')->id);
    }

    public function testEndorsements(): void
    {
        $endorsement = $this->endorsement();

        self::assertSame([], Endorsements::fields([]));
        self::assertSame(['endorsement' => [$endorsement]], Endorsements::fields([$endorsement]));
        self::assertSame(['endorsementJwt' => [self::JWT]], Endorsements::fields([self::JWT]));

        foreach ([['type' => ['VerifiableCredential'], 'proof' => []], ['type' => 'EndorsementCredential', 'proof' => 'x'], ['type' => 'EndorsementCredential']] as $refused) {
            try {
                Endorsements::fields([$refused]);
                self::fail('The endorsement was expected to be refused.');
            } catch (InvalidBadge) {
                self::assertIsArray($refused);
            }
        }

        self::assertSame(['endorsement' => [['type' => 'EndorsementCredential', 'proof' => []]]], Endorsements::fields([['type' => 'EndorsementCredential', 'proof' => []]]));

        foreach (['a.b', 'a.b.c.d'] as $token) {
            try {
                Endorsements::fields([$token]);
                self::fail('The token was expected to be refused.');
            } catch (InvalidBadge $refused) {
                self::assertSame('An endorsement given as a string is a compact JSON Web Token.', $refused->getMessage());
            }
        }
    }

    public function testTheProfileDocument(): void
    {
        $key = Ed25519Signer::fromSeed(str_repeat("\1", 32))->verificationKey();
        $issuer = new Issuer('https://example.org/issuer', 'Example');

        self::assertSame(['@context' => [Context::CREDENTIALS_V2, Context::OPEN_BADGES, Context::MULTIKEY], 'id' => 'https://example.org/issuer', 'type' => ['Profile'], 'name' => 'Example'], $issuer->profileDocument());
        self::assertSame([
            '@context' => [Context::CREDENTIALS_V2, Context::OPEN_BADGES, Context::MULTIKEY],
            'id' => 'https://example.org/issuer',
            'type' => ['Profile'],
            'name' => 'Example',
            'verificationMethod' => [['id' => 'https://example.org/issuer#key-1', 'type' => 'Multikey', 'controller' => 'https://example.org/issuer', 'publicKeyMultibase' => $key->toMultibase()]],
            'assertionMethod' => ['https://example.org/issuer#key-1'],
        ], $issuer->profileDocument(['https://example.org/issuer#key-1' => $key]));

        $this->expectExceptionObject(InvalidBadge::because('A verification method of the issuer is a fragment of its identifier; "https://example.org/issuerx#key-1" is not.'));
        $issuer->profileDocument(['https://example.org/issuerx#key-1' => $key]);
    }

    public function testARecipientIsIdentified(): void
    {
        self::assertTrue(Recipient::identifies(Recipient::did('did:example:ada')->toArray(), 'did:example:ada'));
        self::assertFalse(Recipient::identifies(Recipient::did('did:example:ada')->toArray(), 'did:example:bob'));
        self::assertTrue(Recipient::identifies(Recipient::email('Ada@Example.org', 'saltsalt')->toArray(), ' ada@example.ORG '));
        self::assertFalse(Recipient::identifies(Recipient::email('ada@example.org', 'saltsalt')->toArray(), 'bob@example.org'));
        self::assertTrue(Recipient::identifies(Recipient::url('https://ada.example.org/', 'saltsalt')->toArray(), 'https://ada.example.org/'));
        self::assertFalse(Recipient::identifies(Recipient::url('https://ada.example.org/', 'saltsalt')->toArray(), 'https://ADA.example.org/'));

        $hash = 'sha256$'.hash('sha256', 'ada@example.org');
        self::assertTrue(Recipient::identifies(['identifier' => [['identityHash' => strtoupper($hash), 'identityType' => 'emailAddress', 'hashed' => true]]], 'ada@example.org'));
        self::assertTrue(Recipient::identifies(['identifier' => [['identityHash' => 'ada@example.org', 'identityType' => 'emailAddress', 'hashed' => false]]], 'ada@example.org'));
        self::assertFalse(Recipient::identifies(['identifier' => [['identityHash' => 'ada@example.org', 'identityType' => 'emailAddress']]], 'ADA@example.org'));
        self::assertFalse(Recipient::identifies(['identifier' => [['identityHash' => $hash, 'hashed' => true]]], 'ada@example.org'));
        self::assertFalse(Recipient::identifies(['identifier' => [['identityType' => 'emailAddress', 'hashed' => true]]], 'ada@example.org'));
        self::assertFalse(Recipient::identifies(['identifier' => ['x', ['identityHash' => 'ada', 'identityType' => 'name', 'hashed' => 'yes']]], 'ada@example.org'));
        self::assertTrue(Recipient::identifies(['identifier' => ['x', ['identityHash' => 'ada', 'identityType' => 'name', 'hashed' => 'yes']]], 'ada'));
        self::assertFalse(Recipient::identifies(['id' => 3, 'identifier' => 'x'], '3'));
        self::assertTrue(Recipient::identifies(['identifier' => [['identityHash' => 'sha256$'.hash('sha256', 'ada@example.org'), 'identityType' => 'emailAddress', 'hashed' => true, 'salt' => 3]]], 'ada@example.org'));
    }

    public function testADidHoldsNoCharacterAnIriRefuses(): void
    {
        $this->expectException(InvalidBadge::class);
        Recipient::did('did:example:a<b');
    }

    public function testACredentialCarriesItsStatusAndEndorsements(): void
    {
        $endorsement = $this->endorsement();
        $credential = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), Ed25519Signer::fromSeed(str_repeat("\1", 32)))
            ->credential($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 10:00:00 UTC'), status: new StatusListEntry('https://example.org/status/1', 7), endorsements: [$endorsement, self::JWT]);

        self::assertSame(['id' => 'https://example.org/status/1#7', 'type' => 'BitstringStatusListEntry', 'statusPurpose' => 'revocation', 'statusListIndex' => '7', 'statusListCredential' => 'https://example.org/status/1'], $credential['credentialStatus'] ?? null);
        self::assertSame([$endorsement], $credential['endorsement'] ?? null);
        self::assertSame([self::JWT], $credential['endorsementJwt'] ?? null);
        self::assertSame(['@context', 'id', 'type', 'issuer', 'validFrom', 'name', 'credentialSubject', 'credentialStatus', 'endorsement', 'endorsementJwt'], array_keys($credential));
    }

    public function testACredentialIdentifierIsAUuidOrAnUrl(): void
    {
        Credentials::checkId('https://example.org/credentials/1');
        Credentials::checkId('http://example.org/c?x=1');

        foreach (['urn:uuid:3F6F2C1E-8A4B-4C1D-9E2F-5A6B7C8D9E0F', 'urn:uuid:3f6f2c1e8a4b-4c1d-9e2f-5a6b7c8d9e0f0', 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0', 'https://example.org/<', 'https://', 'urn:x', "https://example.org/x\n", "urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f\n"] as $id) {
            try {
                Credentials::checkId($id);
                self::fail(\sprintf('"%s" was expected to be refused.', $id));
            } catch (InvalidBadge $refused) {
                self::assertSame(\sprintf('The identifier of a credential is a "urn:uuid:" or an absolute HTTP URL; "%s" is not.', $id), $refused->getMessage());
            }
        }
    }

    public function testThePeriodIsWrittenInUtc(): void
    {
        self::assertSame(['validFrom' => '2026-11-07T15:00:00Z'], Credentials::period(new DateTimeImmutable('2026-11-07 10:00:00 America/Toronto'), null));
        self::assertSame(['validFrom' => '2026-11-07T15:00:00Z', 'validUntil' => '2026-11-07T15:00:01Z'], Credentials::period(new DateTimeImmutable('2026-11-07 10:00:00 America/Toronto'), new DateTimeImmutable('2026-11-07 15:00:01 UTC')));
        self::assertSame('2026-11-07T15:00:00Z', Credentials::moment(new DateTimeImmutable('2026-11-07 15:00:00.75 UTC')));
    }

    public function testAnEndorsementIsBuilt(): void
    {
        $endorser = new Issuer('https://partner.example/', 'Partner');
        $builder = new EndorsementIssuer($endorser);

        self::assertSame([
            '@context' => Context::CREDENTIAL,
            'id' => self::ID,
            'type' => ['VerifiableCredential', 'EndorsementCredential'],
            'name' => 'Endorsement of Gold',
            'issuer' => $endorser->toArray(),
            'validFrom' => '2026-01-01T00:00:00Z',
            'validUntil' => '2027-01-01T00:00:00Z',
            'credentialSubject' => ['id' => 'https://example.org/badges/gold', 'type' => ['EndorsementSubject'], 'endorsementComment' => 'Well run.'],
        ], $builder->credential('https://example.org/badges/gold', 'Endorsement of Gold', 'Well run.', self::ID, new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC')));

        self::assertSame(['id' => 'did:example:ada', 'type' => ['EndorsementSubject']], $builder->credential('did:example:ada', 'E', ' ', self::ID, new DateTimeImmutable())['credentialSubject'] ?? null);
        self::assertSame(['id' => 'urn:uuid:x', 'type' => ['EndorsementSubject']], $builder->credential('urn:uuid:x', 'E', '', self::ID, new DateTimeImmutable())['credentialSubject'] ?? null);
    }

    public function testAnEndorsementNamesWhatItSupports(): void
    {
        $builder = new EndorsementIssuer(new Issuer('https://partner.example/', 'Partner'));

        foreach (['gold', 'x https://example.org', "https://example.org\n", 'did:Example:1', 'urn:isbn:1'] as $endorsed) {
            try {
                $builder->credential($endorsed, 'E', '', self::ID, new DateTimeImmutable());
                self::fail(\sprintf('"%s" was expected to be refused.', $endorsed));
            } catch (InvalidBadge $refused) {
                self::assertSame(\sprintf('What an endorsement supports is identified by a URL, a "urn:uuid:" or a DID; "%s" is not.', $endorsed), $refused->getMessage());
            }
        }
    }

    public function testAnEndorsementHasANameAndAnIdentifier(): void
    {
        $builder = new EndorsementIssuer(new Issuer('https://partner.example/', 'Partner'));

        try {
            $builder->credential('https://example.org/gold', ' ', '', self::ID, new DateTimeImmutable());
            self::fail('A nameless endorsement was expected to be refused.');
        } catch (InvalidBadge $invalidBadge) {
            self::assertSame('An endorsement has a name.', $invalidBadge->getMessage());
        }

        $this->expectException(InvalidBadge::class);
        $builder->credential('https://example.org/gold', 'E', '', 'urn:uuid:1', new DateTimeImmutable());
    }

    private function achievement(): Achievement
    {
        return new Achievement('https://example.org/badges/gold', 'Gold', 'Fifty comments.', 'Comment on fifty news items.');
    }

    /**
     * @return array<string, mixed>
     */
    private function endorsement(): array
    {
        return ['type' => ['VerifiableCredential', 'EndorsementCredential'], 'proof' => ['type' => 'DataIntegrityProof']];
    }
}
