<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use Closure;
use OpenSSLAsymmetricKey;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationPolicy;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationStatement;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationType;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AuthenticatorData;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\AttestationCertificates;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Authenticator;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Cbor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The attestation of a new passkey: what a statement proves, the certificates it is checked against, and the policy
 * of the makes, the attestations and the synced passkeys a site accepts.
 */
#[CoversClass(AttestationStatement::class)]
#[CoversClass(AttestationPolicy::class)]
#[CoversClass(AttestationType::class)]
#[CoversClass(RelyingParty::class)]
#[CoversClass(RegisteredCredential::class)]
final class AttestationTest extends TestCase
{
    private const string AAGUID = "\x01\x23\x45\x67\x89\xab\xcd\xef\x01\x23\x45\x67\x89\xab\xcd\xef";

    private const array LEAF = ['countryName' => 'CA', 'organizationName' => 'Maker', 'organizationalUnitName' => 'Authenticator Attestation', 'commonName' => 'Maker Key'];

    /** @var array{cert: string, key: OpenSSLAsymmetricKey} */
    private static array $root;

    /** @var array{cert: string, key: OpenSSLAsymmetricKey} */
    private static array $intermediate;

    public static function setUpBeforeClass(): void
    {
        self::$root = AttestationCertificates::issue(['commonName' => 'Maker Root']);
        self::$intermediate = AttestationCertificates::issue(['commonName' => 'Maker Intermediate'], self::$root);
    }

    public function testAStatementThatIsNotAMapIsRefusedWhateverItsFormat(): void
    {
        foreach (['none', 'packed', 'tpm'] as $format) {
            try {
                $this->party(new AttestationPolicy('direct'))->verifyRegistration(...$this->register(new Authenticator(), static fn (): string => 'a text', $format));
                self::fail($format);
            } catch (MalformedInput $malformed) {
                self::assertSame('An attestation statement is a map.', $malformed->getMessage(), $format);
            }
        }
    }

    public function testTheAaguidsOfThePolicyAreExactlyThirtySixCharacters(): void
    {
        $aaguid = '01234567-89ab-cdef-0123-456789abcdef';

        self::assertSame([strtoupper($aaguid)], new AttestationPolicy('direct', allowedAaguids: [strtoupper($aaguid)])->allowedAaguids);

        foreach (['x'.$aaguid, $aaguid."\n", $aaguid.'0'] as $broken) {
            try {
                new AttestationPolicy('direct', deniedAaguids: [$broken]);
                self::fail($broken);
            } catch (InvalidValue $invalid) {
                self::assertStringContainsString('is not an AAGUID', $invalid->getMessage());
            }
        }
    }

    public function testTheBrowserIsAskedForTheAttestationOfThePolicy(): void
    {
        self::assertSame('none', $this->party()->creationOptions('c', 'u', 'n', 'd')['attestation']);
        self::assertSame('direct', $this->party(new AttestationPolicy('direct'))->creationOptions('c', 'u', 'n', 'd')['attestation']);
    }

    public function testWithoutConveyanceAStatementIsRecordedNotRead(): void
    {
        $authenticator = new Authenticator();
        $broken = $this->register($authenticator, static fn (): array => ['alg' => -7, 'sig' => Cbor::bytes('not a signature')]);
        $credential = $this->party()->verifyRegistration($broken[0], $broken[1], 'challenge');

        self::assertSame(['packed', AttestationType::Unverified], [$credential->attestationFormat, $credential->attestationType]);
        $none = $this->party()->verifyRegistration(...$this->register($authenticator, static fn (): array => [], 'none'));
        self::assertSame(['none', AttestationType::None], [$none->attestationFormat, $none->attestationType]);
        $long = $this->party()->verifyRegistration(...$this->register($authenticator, static fn (): array => [], str_repeat('f', 40)));
        self::assertSame(str_repeat('f', 32), $long->attestationFormat);
        self::assertSame(self::AAGUID, $long->aaguid);
    }

    public function testASelfAttestationIsSignedByThePasskey(): void
    {
        $authenticator = new Authenticator();
        $direct = $this->party(new AttestationPolicy('direct'));
        $credential = $direct->verifyRegistration(...$this->register($authenticator, static fn (string $signed, Authenticator $self): array => ['alg' => -7, 'sig' => Cbor::bytes($self->signature($signed))]));

        self::assertSame(['packed', AttestationType::Self], [$credential->attestationFormat, $credential->attestationType]);

        foreach ([
            static fn (string $signed, Authenticator $self): array => ['alg' => -257, 'sig' => Cbor::bytes($self->signature($signed))],
            static fn (string $signed, Authenticator $self): array => ['alg' => -7, 'sig' => Cbor::bytes($self->signature($signed.'x'))],
        ] as $statement) {
            try {
                $direct->verifyRegistration(...$this->register($authenticator, $statement));
                self::fail('Accepted.');
            } catch (VerificationFailed $failed) {
                self::assertStringContainsString('self attestation', $failed->getMessage());
            }
        }

        self::assertSame(AttestationType::None, $direct->verifyRegistration(...$this->register($authenticator, static fn (): array => [], 'none'))->attestationType);
        self::assertSame(AttestationType::Unverified, $direct->verifyRegistration(...$this->register($authenticator, static fn (): array => ['ver' => '2.0'], 'tpm'))->attestationType);
    }

    public function testACertificateChainReachingATrustedRootIsTrusted(): void
    {
        $leaf = AttestationCertificates::issue(self::LEAF, self::$intermediate, false, self::AAGUID);
        $chain = [AttestationCertificates::der($leaf['cert']), AttestationCertificates::der(self::$intermediate['cert'])];
        $statement = $this->signedBy($leaf['key'], $chain);
        $trusted = $this->party(new AttestationPolicy('direct', [self::$root['cert']]));

        self::assertSame(AttestationType::Trusted, $trusted->verifyRegistration(...$this->register(new Authenticator(), $statement))->attestationType);
        self::assertSame(AttestationType::Unverified, $this->party(new AttestationPolicy('direct'))->verifyRegistration(...$this->register(new Authenticator(), $statement))->attestationType);
        self::assertSame(AttestationType::Unverified, $this->party(new AttestationPolicy('direct', [AttestationCertificates::issue(['commonName' => 'Other Root'])['cert'], 'not a certificate']))->verifyRegistration(...$this->register(new Authenticator(), $statement))->attestationType);
        self::assertSame(AttestationType::Unverified, $trusted->verifyRegistration(...[...$this->register(new Authenticator(), $statement), time() + 40 * 86_400])->attestationType, 'Expired.');
        self::assertSame(AttestationType::Unverified, $trusted->verifyRegistration(...[...$this->register(new Authenticator(), $statement), time() - 86_400])->attestationType, 'Not yet valid.');
        self::assertSame(AttestationType::Unverified, $trusted->verifyRegistration(...$this->register(new Authenticator(), $this->signedBy($leaf['key'], [$chain[0]])))->attestationType, 'The intermediate is missing.');

        $direct = AttestationCertificates::issue(self::LEAF, self::$root, false);
        self::assertSame(AttestationType::Trusted, $trusted->verifyRegistration(...$this->register(new Authenticator(), $this->signedBy($direct['key'], [AttestationCertificates::der($direct['cert'])])))->attestationType);

        $notAuthority = AttestationCertificates::issue(['commonName' => 'Not an authority'], self::$root, false);
        $under = AttestationCertificates::issue(self::LEAF, $notAuthority, false);
        self::assertSame(AttestationType::Unverified, $trusted->verifyRegistration(...$this->register(new Authenticator(), $this->signedBy($under['key'], [AttestationCertificates::der($under['cert']), AttestationCertificates::der($notAuthority['cert'])])))->attestationType);
    }

    public function testTheCertificatesAreValidFromTheirFirstToTheirLastSecond(): void
    {
        $leaf = AttestationCertificates::issue(self::LEAF, self::$root, false, days: 10);
        $statement = self::signedBy($leaf['key'], [AttestationCertificates::der($leaf['cert'])]);
        $times = openssl_x509_parse($leaf['cert']);
        $root = openssl_x509_parse(self::$root['cert']);
        self::assertIsArray($times);
        self::assertIsArray($root);
        self::assertIsInt($times['validFrom_time_t']);
        self::assertIsInt($times['validTo_time_t']);
        self::assertIsInt($root['validFrom_time_t']);
        self::assertIsInt($root['validTo_time_t']);
        $party = $this->party(new AttestationPolicy('direct', [self::$root['cert']]));
        $at = fn (int $now): AttestationType => $party->verifyRegistration(...[...$this->register(new Authenticator(), $statement), $now])->attestationType;

        self::assertSame(AttestationType::Trusted, $at(max($times['validFrom_time_t'], $root['validFrom_time_t'])));
        self::assertSame(AttestationType::Trusted, $at($times['validTo_time_t']));
        self::assertSame(AttestationType::Unverified, $at($times['validTo_time_t'] + 1));
        self::assertSame(AttestationType::Unverified, $at($times['validFrom_time_t'] - 1));

        $late = AttestationCertificates::issue(self::LEAF, self::$root, false, days: 60);
        $lateTimes = openssl_x509_parse($late['cert']);
        self::assertIsArray($lateTimes);
        self::assertIsInt($lateTimes['validTo_time_t']);
        $lateStatement = self::signedBy($late['key'], [AttestationCertificates::der($late['cert'])]);
        self::assertSame(AttestationType::Trusted, $party->verifyRegistration(...[...$this->register(new Authenticator(), $lateStatement), $root['validTo_time_t']])->attestationType);
        self::assertSame(AttestationType::Unverified, $party->verifyRegistration(...[...$this->register(new Authenticator(), $lateStatement), $root['validTo_time_t'] + 1])->attestationType, 'The root expired before the leaf.');
    }

    public function testAnAttestationCertificateBreakingTheRulesIsRefused(): void
    {
        $party = $this->party(new AttestationPolicy('direct', [self::$root['cert']]));
        $cases = [
            'another unit' => AttestationCertificates::issue(['organizationalUnitName' => 'Other'] + self::LEAF, self::$root, false),
            'an authority' => AttestationCertificates::issue(self::LEAF, self::$root, true),
            'another AAGUID' => AttestationCertificates::issue(self::LEAF, self::$root, false, str_repeat("\x09", 16)),
        ];

        foreach ($cases as $case => $certificate) {
            try {
                $party->verifyRegistration(...$this->register(new Authenticator(), $this->signedBy($certificate['key'], [AttestationCertificates::der($certificate['cert'])])));
                self::fail('Accepted '.$case.'.');
            } catch (VerificationFailed $failed) {
                self::assertStringContainsString('attestation certificate', $failed->getMessage(), $case);
            }
        }

        $leaf = AttestationCertificates::issue(self::LEAF, self::$root, false);

        try {
            $party->verifyRegistration(...$this->register(new Authenticator(), $this->signedBy(AttestationCertificates::key(), [AttestationCertificates::der($leaf['cert'])])));
            self::fail('Accepted a signature of another key.');
        } catch (VerificationFailed $verificationFailed) {
            self::assertStringContainsString('not signed by its certificate', $verificationFailed->getMessage());
        }

        self::assertSame(AttestationType::Unverified, $party->verifyRegistration(...$this->register(new Authenticator(), static fn (): array => ['alg' => -8, 'sig' => Cbor::bytes('s'), 'x5c' => [Cbor::bytes(AttestationCertificates::der($leaf['cert']))]]))->attestationType);
    }

    public function testAStatementOutOfShapeIsRefused(): void
    {
        $authenticator = new Authenticator();
        $authData = $authenticator->authenticatorData(0x45, self::AAGUID.pack('n', \strlen($authenticator->credentialId)).$authenticator->credentialId.$authenticator->coseKey());
        $data = AuthenticatorData::parse($authData);
        $der = AttestationCertificates::der(self::$root['cert']);

        foreach ([
            ['none', 'not a map'],
            ['none', ['alg' => -7]],
            ['packed', []],
            ['packed', ['alg' => 7, 'sig' => 's']],
            ['packed', ['alg' => 'ES256', 'sig' => 's']],
            ['packed', ['alg' => -7]],
            ['packed', ['alg' => -7, 'sig' => '']],
            ['packed', ['alg' => -7, 'sig' => 's', 'x5c' => []]],
            ['packed', ['alg' => -7, 'sig' => 's', 'x5c' => 'certificate']],
            ['packed', ['alg' => -7, 'sig' => 's', 'x5c' => array_fill(0, 6, $der)]],
            ['packed', ['alg' => -7, 'sig' => 's', 'x5c' => [1 => $der]]],
            ['packed', ['alg' => -7, 'sig' => 's', 'x5c' => ['']]],
            ['packed', ['alg' => -7, 'sig' => 's', 'x5c' => [5]]],
            ['packed', ['alg' => -7, 'sig' => 's', 'x5c' => ['not der']]],
        ] as [$format, $statement]) {
            try {
                AttestationStatement::verify($format, $statement, $authData, str_repeat("\0", 32), $data, [], time());
                self::fail('Accepted '.json_encode($statement));
            } catch (MalformedInput $malformed) {
                self::assertNotSame('', $malformed->getMessage());
            }
        }

        $without = AuthenticatorData::parse($authenticator->authenticatorData(0x05));

        try {
            AttestationStatement::verify('packed', ['alg' => -7, 'sig' => 's'], $authData, '', $without, [], time());
            self::fail('Accepted a statement without a credential.');
        } catch (MalformedInput $malformedInput) {
            self::assertStringContainsString('credential', $malformedInput->getMessage());
        }

        try {
            AttestationStatement::verify('packed', ['alg' => -7, 'sig' => 's', 'x5c' => array_fill(0, 5, $der)], $authData, '', $data, [], time());
            self::fail('Accepted a signature that does not verify.');
        } catch (VerificationFailed $verificationFailed) {
            self::assertStringContainsString('not signed by its certificate', $verificationFailed->getMessage(), 'Five certificates are read.');
        }
    }

    public function testThePolicyOfTheMakesTheAttestationsAndTheSyncedPasskeys(): void
    {
        $policy = new AttestationPolicy();
        $policy->assert(self::AAGUID, AttestationType::None, true);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', AttestationPolicy::format(self::AAGUID));
        self::assertSame(AttestationPolicy::NO_AAGUID, AttestationPolicy::format(str_repeat("\0", 16)));

        $refusals = [
            [new AttestationPolicy('direct', ['root'], true), self::AAGUID, AttestationType::Self, false, 'trusted root'],
            [new AttestationPolicy(allowedAaguids: ['11111111-1111-1111-1111-111111111111']), self::AAGUID, AttestationType::None, false, 'not accepted'],
            [new AttestationPolicy(deniedAaguids: ['01234567-89AB-CDEF-0123-456789ABCDEF']), self::AAGUID, AttestationType::None, false, 'not accepted'],
            [new AttestationPolicy(backup: 'forbidden'), self::AAGUID, AttestationType::None, true, 'forbidden'],
            [new AttestationPolicy(backup: 'required'), self::AAGUID, AttestationType::None, false, 'required'],
        ];

        foreach ($refusals as [$refusing, $aaguid, $type, $eligible, $message]) {
            try {
                $refusing->assert($aaguid, $type, $eligible);
                self::fail('Accepted: '.$message);
            } catch (VerificationFailed $failed) {
                self::assertStringContainsString($message, $failed->getMessage());
            }
        }

        $accepted = 0;

        foreach ([
            [new AttestationPolicy('direct', ['root'], true), AttestationType::Trusted, true],
            [new AttestationPolicy(allowedAaguids: ['01234567-89AB-cdef-0123-456789abcdef']), AttestationType::None, false],
            [new AttestationPolicy(deniedAaguids: ['11111111-1111-1111-1111-111111111111']), AttestationType::None, false],
            [new AttestationPolicy(backup: 'forbidden'), AttestationType::None, false],
            [new AttestationPolicy(backup: 'required'), AttestationType::None, true],
        ] as [$accepting, $type, $eligible]) {
            $accepting->assert(self::AAGUID, $type, $eligible);
            ++$accepted;
        }

        self::assertSame(5, $accepted);

        foreach ([
            static fn (): AttestationPolicy => new AttestationPolicy('always'),
            static fn (): AttestationPolicy => new AttestationPolicy(backup: 'sometimes'),
            static fn (): AttestationPolicy => new AttestationPolicy('direct', [], true),
            static fn (): AttestationPolicy => new AttestationPolicy('none', ['root'], true),
            static fn (): AttestationPolicy => new AttestationPolicy(allowedAaguids: ['0123']),
            static fn (): AttestationPolicy => new AttestationPolicy(deniedAaguids: ['01234567-89ab-cdef-0123-456789abcdef ']),
            static fn (): AttestationPolicy => new AttestationPolicy(deniedAaguids: ['g1234567-89ab-cdef-0123-456789abcdef']),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Accepted.');
            } catch (InvalidValue $refusal) {
                self::assertNotSame('', $refusal->getMessage());
            }
        }
    }

    /**
     * @param list<string> $chain DER certificates, the attestation certificate first
     *
     * @return Closure(string): array<string, mixed>
     */
    private function signedBy(OpenSSLAsymmetricKey $key, array $chain): Closure
    {
        return static function (string $signed) use ($key, $chain): array {
            openssl_sign($signed, $signature, $key, \OPENSSL_ALGO_SHA256);
            \assert(\is_string($signature));

            return ['alg' => -7, 'sig' => Cbor::bytes($signature), 'x5c' => array_map(Cbor::bytes(...), $chain)];
        };
    }

    private function party(AttestationPolicy $policy = new AttestationPolicy()): RelyingParty
    {
        return new RelyingParty('example.com', 'Example', ['https://example.com'], attestation: $policy);
    }

    /**
     * @param Closure(string, Authenticator): mixed $statement
     *
     * @return array{string, string, string} the client data, the attestation object and the challenge
     */
    private function register(Authenticator $authenticator, Closure $statement, string $format = 'packed'): array
    {
        $json = json_decode($authenticator->registerAttested('challenge', $statement, $format, self::AAGUID), true);
        \assert(\is_array($json) && \is_array($json['response']) && \is_string($json['response']['clientDataJSON']) && \is_string($json['response']['attestationObject']));

        return [Base64Url::decode($json['response']['clientDataJSON']), Base64Url::decode($json['response']['attestationObject']), 'challenge'];
    }
}
