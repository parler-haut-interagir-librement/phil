<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit\Rules;

use DateTimeImmutable;
use Override;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\CatalogEntry;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CredentialResolver;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\CredentialStatus;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointDesks;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointRules;
use Phil\CheckinBundle\Infrastructure\Rules\ConfiguredAdmissionRules;
use Phil\CheckinBundle\Infrastructure\Rules\RuledCredentials;
use Phil\CheckinBundle\Tests\Unit\Fixture\FixedCredentials;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Clock\MockClock;

#[CoversClass(RuledCredentials::class)]
#[CoversClass(CheckpointDesks::class)]
final class RuledCredentialsTest extends TestCase
{
    public function testACredentialTheRuleRefusesNoLongerOpensTheCheckpointAndKeepsTheRest(): void
    {
        $open = new DateTimeImmutable('2026-11-01 00:00 UTC');
        $close = new DateTimeImmutable('2026-11-30 00:00 UTC');
        $refused = Credential::of('T-9', null, [CheckpointId::of('hall'), CheckpointId::of('bar')], $open, $close, 3, CredentialStatus::Revoked);
        $resolver = new readonly class($refused) implements CredentialResolver {
            private FixedCredentials $fixed;

            public function __construct(private Credential $refused)
            {
                $this->fixed = new FixedCredentials();
            }

            #[Override]
            public function resolve(CredentialCode $code): ?Credential
            {
                return 'CODE-9' === $code->value ? $this->refused : $this->fixed->resolve($code);
            }
        };
        $credentials = new RuledCredentials($this->rules(), CheckpointId::of('bar'), $resolver);

        $ruled = $credentials->resolve(CredentialCode::of('CODE-9'));
        self::assertInstanceOf(Credential::class, $ruled);
        self::assertFalse($ruled->opens(CheckpointId::of('bar')));
        self::assertEquals([CheckpointId::of(RuledCredentials::CLOSED)], $ruled->checkpoints);
        self::assertSame(['T-9', null, $open, $close, 3, CredentialStatus::Revoked], [$ruled->id, $ruled->label, $ruled->validFrom, $ruled->validUntil, $ruled->maxEntries, $ruled->status]);

        $admitted = $credentials->resolve(CredentialCode::of('CODE-1'));
        self::assertInstanceOf(Credential::class, $admitted);
        self::assertTrue($admitted->opens(CheckpointId::of('bar')));
        self::assertSame('T-1', $admitted->id);
        self::assertNull($credentials->resolve(CredentialCode::of('NOPE')));
    }

    public function testACredentialThatDoesNotOpenTheCheckpointIsLeftAsItIs(): void
    {
        $elsewhere = Credential::of('T-8', checkpoints: [CheckpointId::of('stage')]);
        $resolver = new readonly class($elsewhere) implements CredentialResolver {
            public function __construct(private Credential $elsewhere)
            {
            }

            #[Override]
            public function resolve(CredentialCode $code): Credential
            {
                return $this->elsewhere;
            }
        };

        self::assertSame($elsewhere, new RuledCredentials($this->rules(), CheckpointId::of('bar'), $resolver)->resolve(CredentialCode::of('X')));
    }

    public function testTheClosedCheckpointIsNeverTheOneScanned(): void
    {
        $rules = new CheckpointRules(new ConfiguredAdmissionRules([RuledCredentials::CLOSED => 'false']), new MockClock());
        $ruled = new RuledCredentials($rules, CheckpointId::of(RuledCredentials::CLOSED), new FixedCredentials())->resolve(CredentialCode::of('CODE-1'));

        self::assertInstanceOf(Credential::class, $ruled);
        self::assertFalse($ruled->opens(CheckpointId::of(RuledCredentials::CLOSED)));
        self::assertEquals([CheckpointId::of(RuledCredentials::CLOSED.'-')], $ruled->checkpoints);
    }

    public function testTheCatalogKeepsTheEntriesTheRuleAdmitsAndFindsCredentialsUnchanged(): void
    {
        $catalog = new FixedCredentials();
        $credentials = new RuledCredentials($this->rules(), CheckpointId::of('bar'), catalog: $catalog);

        self::assertSame(['T-1'], array_map(static fn (CatalogEntry $entry): string => $entry->credential->id, iterator_to_array($credentials->forCheckpoint(CheckpointId::of('bar')), false)));
        self::assertSame(['T-1', 'T-2'], array_map(static fn (CatalogEntry $entry): string => $entry->credential->id, iterator_to_array($credentials->forCheckpoint(CheckpointId::of('hall')), false)));
        self::assertSame($catalog->find('T-2'), $credentials->find('T-2'));
        self::assertNull($credentials->find('T-9'));

        $empty = new RuledCredentials($this->rules(), CheckpointId::of('bar'));
        self::assertSame([], iterator_to_array($empty->forCheckpoint(CheckpointId::of('bar')), false));
        self::assertNull($empty->find('T-1'));
        self::assertNull($empty->resolve(CredentialCode::of('CODE-1')));
    }

    public function testADeskDecidesAnEntryOnTheRuleAndAnExitWithoutIt(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $desks = $this->desks($ledger);

        self::assertSame('wrong_checkpoint', $desks->desk(CheckpointId::of('bar'))->scan(CredentialCode::of('CODE-2'), CheckpointId::of('bar'), DeviceId::of('d1'))->outcome->value);
        self::assertSame('admitted', $desks->desk(CheckpointId::of('hall'))->scan(CredentialCode::of('CODE-2'), CheckpointId::of('hall'), DeviceId::of('d1'))->outcome->value);
        self::assertSame('admitted', $desks->desk(CheckpointId::of('bar'), Direction::In)->scan(CredentialCode::of('CODE-1'), CheckpointId::of('bar'), DeviceId::of('d1'))->outcome->value);
        self::assertSame('checked_out', $desks->desk(CheckpointId::of('bar'), Direction::Out)->scan(CredentialCode::of('CODE-2'), CheckpointId::of('bar'), DeviceId::of('d1'), Direction::Out)->outcome->value);
    }

    public function testAManifestIsIssuedOnTheRuleWithTheBoundsGiven(): void
    {
        $desks = $this->desks(new InMemoryCheckInLedger());

        $bar = $desks->manifests(CheckpointId::of('bar'))?->issue(CheckpointId::of('bar'), DeviceId::of('d1'))->toArray();
        $hall = $desks->manifests(CheckpointId::of('hall'))?->issue(CheckpointId::of('hall'), DeviceId::of('d1'))->toArray();
        self::assertIsArray($bar);
        self::assertIsArray($hall);
        self::assertCount(1, $bar['credentials']);
        self::assertCount(2, $hall['credentials']);
        self::assertSame(3_600, $bar['expiresAt'] - $bar['issuedAt']);
        self::assertSame('Élise', $bar['credentials'][0][2]);

        $online = new CheckpointDesks(new FixedCredentials(), new InMemoryCheckInLedger(), new MockClock(), new AdmissionPolicy(), $this->rules());
        self::assertNull($online->manifests(CheckpointId::of('hall')));
    }

    public function testTheDefaultsOfTheManifestAreTheDocumentedOnes(): void
    {
        $defaults = [];

        foreach (new ReflectionMethod(CheckpointDesks::class, '__construct')->getParameters() as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                $defaults[$parameter->getName()] = $parameter->getDefaultValue();
            }
        }

        self::assertSame(['catalog' => null, 'manifestSeconds' => 43_200, 'maxCredentials' => 10_000, 'labels' => false, 'minCodeBits' => 64], $defaults);
    }

    private function desks(InMemoryCheckInLedger $ledger): CheckpointDesks
    {
        return new CheckpointDesks(new FixedCredentials(), $ledger, new MockClock('2026-11-08 10:00 UTC'), new AdmissionPolicy(), $this->rules(), new FixedCredentials(), 3_600, 2, true, 0);
    }

    private function rules(): CheckpointRules
    {
        return new CheckpointRules(new ConfiguredAdmissionRules(['bar' => 'credential.label != null']), new MockClock());
    }
}
