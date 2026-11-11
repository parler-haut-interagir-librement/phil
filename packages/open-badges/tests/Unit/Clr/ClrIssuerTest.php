<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Clr;

use DateTimeImmutable;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Clr\ClrIssuer;
use Phil\OpenBadges\Context;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Recipient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClrIssuer::class)]
final class ClrIssuerTest extends TestCase
{
    private const string ID = 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e00';

    private const string METHOD = 'https://example.org/issuer#key-1';

    public function testARecordGathersCredentials(): void
    {
        [$secured, $token] = $this->credentials();
        $issuer = new Issuer('https://example.org/issuer', 'Example');
        $learner = Recipient::did('did:example:ada');
        $record = new ClrIssuer($issuer)->credential($learner, [$secured, $token], 'Record of Ada', self::ID, new DateTimeImmutable('2026-11-08 UTC'), new DateTimeImmutable('2027-11-08 UTC'));

        self::assertSame([
            '@context' => [Context::CREDENTIALS_V2, Context::CLR, Context::OPEN_BADGES],
            'id' => self::ID,
            'type' => ['VerifiableCredential', 'ClrCredential'],
            'issuer' => $issuer->toArray(),
            'validFrom' => '2026-11-08T00:00:00Z',
            'validUntil' => '2027-11-08T00:00:00Z',
            'name' => 'Record of Ada',
            'credentialSubject' => [
                'type' => ['ClrSubject'],
                'id' => 'did:example:ada',
                'verifiableCredential' => [$secured, ['@context' => Context::CREDENTIALS_V2, 'id' => 'data:application/vc+jwt,'.$token, 'type' => 'EnvelopedVerifiableCredential']],
            ],
        ], $record);

        $suite = new EddsaRdfc2022();
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $signed = $suite->sign($record, $signer, self::METHOD, new DateTimeImmutable('2026-11-08 UTC'));
        self::assertSame($record, $suite->verify($signed, new DidKeyMethods([self::METHOD => $signer->verificationKey()])));
        self::assertStringContainsString('<https://purl.imsglobal.org/spec/vc/clr/vocab.html#verifiableCredential> <data:application/vc+jwt,'.$token.'>', $suite->canonicalize($record));
    }

    public function testAPartialRecord(): void
    {
        [$secured] = $this->credentials();
        $record = new ClrIssuer(new Issuer('https://example.org/issuer', 'Example'))->credential(Recipient::did('did:example:ada'), [$secured], 'Some of Ada', self::ID, new DateTimeImmutable(), partial: true);

        self::assertTrue($record['partial'] ?? null);
        self::assertStringContainsString('<https://purl.imsglobal.org/spec/vc/clr/vocab.html#partial> "true"^^<xsd:boolean>', new EddsaRdfc2022()->canonicalize($record));
        self::assertArrayNotHasKey('partial', new ClrIssuer(new Issuer('https://example.org/issuer', 'Example'))->credential(Recipient::did('did:example:ada'), [$secured], 'All of Ada', self::ID, new DateTimeImmutable()));
    }

    public function testARecordIsChecked(): void
    {
        [$secured] = $this->credentials();
        $issuer = new ClrIssuer(new Issuer('https://example.org/issuer', 'Example'));
        $learner = Recipient::did('did:example:ada');
        $cases = [
            'A learner record holds from 1 to 200 credentials.' => [[], 'R', self::ID],
            'A learner record has a name.' => [[$secured], ' ', self::ID],
            'A credential given as a string to a learner record is a compact JSON Web Token.' => [['a.b'], 'R', self::ID],
            'A credential given as a document to a learner record is a verifiable credential with its proof.' => [[['type' => ['VerifiableCredential']]], 'R', self::ID],
            'The identifier of a credential is a "urn:uuid:" or an absolute HTTP URL; "x" is not.' => [[$secured], 'R', 'x'],
        ];

        foreach ($cases as $reason => [$credentials, $name, $id]) {
            try {
                $issuer->credential($learner, $credentials, $name, $id, new DateTimeImmutable());
                self::fail(\sprintf('Expected: %s', $reason));
            } catch (InvalidBadge $refused) {
                self::assertSame($reason, $refused->getMessage());
            }
        }

        try {
            $issuer->credential($learner, [['type' => 'OpenBadgeCredential', 'proof' => []]], 'R', self::ID, new DateTimeImmutable());
            self::fail('A document that is not a verifiable credential was expected to be refused.');
        } catch (InvalidBadge $invalidBadge) {
            self::assertSame('A credential given as a document to a learner record is a verifiable credential with its proof.', $invalidBadge->getMessage());
        }

        self::assertCount(ClrIssuer::MAX_CREDENTIALS, $this->embedded($issuer->credential($learner, array_fill(0, ClrIssuer::MAX_CREDENTIALS, $secured), 'R', self::ID, new DateTimeImmutable())));
        $this->expectExceptionObject(InvalidBadge::because('A learner record holds from 1 to 200 credentials.'));
        $issuer->credential($learner, array_fill(0, ClrIssuer::MAX_CREDENTIALS + 1, $secured), 'R', self::ID, new DateTimeImmutable());
    }

    public function testAnEnvelopedTokenMayHaveAnEmptySignature(): void
    {
        $record = new ClrIssuer(new Issuer('https://example.org/issuer', 'Example'))->credential(Recipient::did('did:example:ada'), ['a-b.c_d.'], 'R', self::ID, new DateTimeImmutable());

        $embedded = $this->embedded($record)[0] ?? null;
        self::assertIsArray($embedded);
        self::assertSame('data:application/vc+jwt,a-b.c_d.', $embedded['id'] ?? null);

        foreach (["a.b.c\n", 'a.b.c.d', 'a b.c.d', '.b.c'] as $token) {
            try {
                new ClrIssuer(new Issuer('https://example.org/issuer', 'Example'))->credential(Recipient::did('did:example:ada'), [$token], 'R', self::ID, new DateTimeImmutable());
                self::fail('The token was expected to be refused.');
            } catch (InvalidBadge $refused) {
                self::assertSame('A credential given as a string to a learner record is a compact JSON Web Token.', $refused->getMessage());
            }
        }
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<array-key, mixed>
     */
    private function embedded(array $record): array
    {
        $subject = $record['credentialSubject'] ?? null;
        self::assertIsArray($subject);
        $credentials = $subject['verifiableCredential'] ?? null;
        self::assertIsArray($credentials);

        return $credentials;
    }

    /**
     * @return array{array<string, mixed>, string}
     */
    private function credentials(): array
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $issuer = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer, self::METHOD);
        $achievement = new Achievement('https://example.org/badges/gold', 'Gold', 'Fifty comments.', 'Comment on fifty items.');
        $credential = $issuer->credential($achievement, Recipient::did('did:example:ada'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f', new DateTimeImmutable('2026-11-07 UTC'));

        return [
            new EddsaRdfc2022()->sign($credential, $signer, self::METHOD, new DateTimeImmutable('2026-11-07 UTC')),
            $issuer->issue($achievement, Recipient::did('did:example:ada'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e01', new DateTimeImmutable('2026-11-07 UTC')),
        ];
    }
}
