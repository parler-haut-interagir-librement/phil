<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Application\Toggle;

use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Application\Toggle\FlagToggler;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleConflict;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleRefused;
use Phil\FeatureFlagsBundle\Domain\Exception\UnknownFlag;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRecord;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CallJournal;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryFlagStore;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\ListAuthorizer;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingAuditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(FlagToggler::class)]
#[CoversClass(ToggleRefused::class)]
#[CoversClass(UnknownFlag::class)]
#[CoversClass(ToggleConflict::class)]
#[CoversClass(ToggleRecord::class)]
final class FlagTogglerTest extends TestCase
{
    private CallJournal $journal;

    private InMemoryFlagStore $store;

    private RecordingAuditor $auditor;

    private FrozenClock $clock;

    private FlagToggler $toggler;

    protected function setUp(): void
    {
        $this->journal = new CallJournal();
        $this->store = new InMemoryFlagStore(['new-checkout' => true], $this->journal);
        $this->auditor = new RecordingAuditor($this->journal);
        $this->clock = new FrozenClock();
        $this->toggler = new FlagToggler(new ListAuthorizer(['operator']), $this->store, $this->auditor, $this->clock);
    }

    public function testAnAuthorizedToggleIsAuditedThenWritten(): void
    {
        self::assertTrue($this->toggler->toggle($this->request('new-checkout', false, 'operator')));

        self::assertSame(['read', 'audit', 'write'], $this->journal->calls);

        self::assertFalse($this->store->flags['new-checkout']);
        self::assertCount(1, $this->auditor->records);
        $record = $this->auditor->records[0];
        self::assertSame('new-checkout', $record->flag->value);
        self::assertSame('operator', $record->actor);
        self::assertTrue($record->before);
        self::assertFalse($record->after);
        self::assertSame('Errors after release', $record->reason->value);
        self::assertEquals($this->clock->now(), $record->at);
    }

    /**
     * No toggle exists without its trace: an auditor that fails stops the toggle before the write.
     */
    public function testAFailingAuditorStopsTheToggleBeforeTheWrite(): void
    {
        $this->auditor->failing = true;

        try {
            $this->toggler->toggle($this->request('new-checkout', false, 'operator'));
            self::fail('The failing auditor should have stopped the toggle.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The audit trail is unavailable.', $runtimeException->getMessage());
        }

        self::assertSame(['read', 'audit'], $this->journal->calls);
        self::assertSame(0, $this->store->writes);
        self::assertTrue($this->store->flags['new-checkout']);
    }

    /**
     * A concurrent toggle between the read and the write: the write is refused rather than applied over a
     * value the audit line does not carry.
     */
    public function testAToggleThatLostARaceIsNotApplied(): void
    {
        $this->store->beforeWrite = static function (InMemoryFlagStore $store): void {
            $store->flags['new-checkout'] = false;
        };

        try {
            $this->toggler->toggle($this->request('new-checkout', false, 'operator'));
            self::fail('The toggle should have lost the race.');
        } catch (ToggleConflict $toggleConflict) {
            self::assertSame('The flag "new-checkout" is no longer active; the toggle was not applied.', $toggleConflict->getMessage());
        }

        self::assertSame(0, $this->store->writes);
        self::assertSame(['read', 'audit', 'write'], $this->journal->calls);
    }

    public function testARefusedToggleWritesAndAuditsNothing(): void
    {
        try {
            $this->toggler->toggle($this->request('new-checkout', false, 'intruder'));
            self::fail('The toggle should have been refused.');
        } catch (ToggleRefused $toggleRefused) {
            self::assertStringContainsString('"new-checkout"', $toggleRefused->getMessage());
        }

        self::assertSame(0, $this->store->writes);
        self::assertSame([], $this->auditor->records);
        self::assertSame([], $this->journal->calls, 'authorization comes before the read');
    }

    public function testAToggleToTheCurrentValueIsANoOp(): void
    {
        self::assertFalse($this->toggler->toggle($this->request('new-checkout', true, 'operator')));

        self::assertSame(0, $this->store->writes);
        self::assertSame([], $this->auditor->records);
        self::assertSame(['read'], $this->journal->calls);
    }

    public function testAToggleNeverCreatesAFlag(): void
    {
        $this->expectException(UnknownFlag::class);
        $this->expectExceptionMessageIsOrContains('No flag named "missing" can be toggled.');

        try {
            $this->toggler->toggle($this->request('missing', true, 'operator'));
        } finally {
            self::assertSame(0, $this->store->writes);
        }
    }

    /**
     * Authorization comes first: an unauthorized actor learns nothing about which flags exist.
     */
    public function testAuthorizationComesBeforeTheLookup(): void
    {
        $this->expectException(ToggleRefused::class);

        $this->toggler->toggle($this->request('missing', true, 'intruder'));
    }

    /**
     * A flag declared elsewhere and not yet in the store is toggled from its declared state, and stored by an
     * insert rather than a compare-and-set.
     */
    public function testADeclaredFlagNotYetStoredIsToggledFromItsDeclaredState(): void
    {
        $toggler = $this->withCatalog(['beta' => ['active' => true]]);

        self::assertTrue($toggler->toggle($this->request('beta', false, 'operator')));
        self::assertFalse($this->store->flags['beta']);
        // The store is read by the toggle, then by the catalog for the declared state.
        self::assertSame(['read', 'read', 'audit', 'insert'], $this->journal->calls);
        self::assertTrue($this->auditor->records[0]->before);
    }

    /**
     * Once the store holds a state, the toggle starts from it, not from what the provider declares.
     */
    public function testTheStoredStateWinsOverTheDeclaredOne(): void
    {
        $this->store->flags['beta'] = true;
        $toggler = $this->withCatalog(['beta' => ['active' => false]]);

        self::assertTrue($toggler->toggle($this->request('beta', false, 'operator')));
        self::assertFalse($this->store->isActive(FlagName::of('beta')));
    }

    public function testADeclaredFlagToggledToItsDeclaredStateIsANoOp(): void
    {
        $toggler = $this->withCatalog(['beta' => ['active' => true]]);

        self::assertFalse($toggler->toggle($this->request('beta', true, 'operator')));
        self::assertArrayNotHasKey('beta', $this->store->flags);
    }

    public function testAFirstToggleThatLostTheInsertIsNotApplied(): void
    {
        $toggler = $this->withCatalog(['beta' => ['active' => true]]);
        $this->store->beforeWrite = static function (InMemoryFlagStore $store): void {
            $store->flags['beta'] = false;
        };

        $this->expectException(ToggleConflict::class);

        $toggler->toggle($this->request('beta', false, 'operator'));
    }

    public function testAFlagNoProviderDeclaresIsStillUnknown(): void
    {
        $this->expectException(UnknownFlag::class);

        $this->withCatalog([])->toggle($this->request('missing', true, 'operator'));
    }

    /**
     * @param array<string, mixed> $flags
     */
    private function withCatalog(array $flags): FlagToggler
    {
        return new FlagToggler(
            new ListAuthorizer(['operator']),
            $this->store,
            $this->auditor,
            $this->clock,
            new FlagCatalog(['yaml' => ConfiguredFlagProvider::fromConfiguration($flags)], $this->store),
        );
    }

    private function request(string $flag, bool $active, string $actor): ToggleRequest
    {
        return new ToggleRequest(FlagName::of($flag), $active, $actor, ToggleReason::of('Errors after release'));
    }
}
