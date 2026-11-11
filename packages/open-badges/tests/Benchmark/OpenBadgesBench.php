<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Benchmark;

use DateTimeImmutable;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Baking\PngBaker;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\DataIntegrity\EddsaRdfc2022;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadges\Tests\Unit\Baking\BakingTest;
use Phil\OpenBadges\Verification\CredentialVerifier;

/**
 * What a verification page does for each credential shown to it — a token or a document checked, a baked image read
 * —, and what issuing costs: a credential signed with a Data Integrity proof, whose canonical form dominates.
 */
final readonly class OpenBadgesBench
{
    private const string METHOD = 'https://example.org/issuer#key-1';

    private CredentialVerifier $verifier;

    private EddsaRdfc2022 $suite;

    private Ed25519Signer $signer;

    /**
     * @var array<string, mixed>
     */
    private array $credential;

    private string $token;

    private string $document;

    private string $baked;

    public function __construct()
    {
        $this->signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $this->suite = new EddsaRdfc2022();
        $this->verifier = new CredentialVerifier(new DidKeyMethods([self::METHOD => $this->signer->verificationKey()]));
        $issuer = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example', 'https://example.org'), $this->signer, self::METHOD);
        $achievement = new Achievement('https://example.org/badges/gold', 'Gold', 'Fifty comments.', 'Comment on fifty news items.', 'https://example.org/gold.png');
        $this->credential = $issuer->credential($achievement, Recipient::email('ada@example.org', 'saltsalt'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f', new DateTimeImmutable('2026-11-07 UTC'));
        $this->token = $issuer->issue($achievement, Recipient::email('ada@example.org', 'saltsalt'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f', new DateTimeImmutable('2026-11-07 UTC'));
        $this->document = (string) json_encode($this->suite->sign($this->credential, $this->signer, self::METHOD, new DateTimeImmutable('2026-11-07 UTC')));
        $this->baked = PngBaker::bake(base64_decode(BakingTest::PNG, true), $this->token);
    }

    public function benchVerifyToken(): void
    {
        $this->verifier->verify($this->token);
    }

    public function benchVerifyDataIntegrity(): void
    {
        $this->verifier->verify($this->document);
    }

    public function benchSignDataIntegrity(): void
    {
        $this->suite->sign($this->credential, $this->signer, self::METHOD, new DateTimeImmutable('2026-11-07 UTC'));
    }

    public function benchExtractFromPng(): void
    {
        PngBaker::extract($this->baked);
    }
}
