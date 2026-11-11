<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Application\Edit;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Application\Edit\FlagEditor;
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRecord;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Edit\RevisedDefinition;
use Phil\FeatureFlagsBundle\Domain\Exception\EditConflict;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleRefused;
use Phil\FeatureFlagsBundle\Domain\Exception\UnknownFlag;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\PercentageRollout;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CallJournal;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryDefinitions;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryFlagStore;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\ListAuthorizer;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingAuditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlagEditor::class)]
#[CoversClass(EditRecord::class)]
final class FlagEditorTest extends TestCase
{
    private CallJournal $journal;

    private InMemoryDefinitions $definitions;

    private InMemoryFlagStore $states;

    private RecordingAuditor $auditor;

    private FlagEditor $editor;

    protected function setUp(): void
    {
        $this->journal = new CallJournal();
        $this->definitions = new InMemoryDefinitions($this->journal);
        $this->states = new InMemoryFlagStore();
        $this->auditor = new RecordingAuditor($this->journal);
        $this->editor = new FlagEditor(
            new ListAuthorizer(['operator']),
            $this->definitions,
            $this->states,
            $this->auditor,
            new FrozenClock(),
            new FlagCatalog([
                'yaml' => ConfiguredFlagProvider::fromConfiguration(['new-checkout' => ['active' => true]]),
                'db' => $this->definitions,
            ], $this->states),
        );
    }

    /**
     * Created inactive whatever the request says, with its state stored inactive, audited before the write.
     */
    public function testACreatedFlagStartsInactive(): void
    {
        $this->editor->create($this->request(new FlagDefinition(FlagName::of('beta'), true)));

        self::assertFalse($this->definitions->definitions['beta']->definition->active);
        self::assertSame(1, $this->definitions->definitions['beta']->revision);
        self::assertSame(['beta' => false], $this->states->flags);
        self::assertSame(['audit', 'write'], $this->journal->calls);

        $record = $this->auditor->edits[0];
        self::assertSame('beta', $record->flag->value);
        self::assertSame('operator', $record->actor);
        self::assertNull($record->before);
        self::assertFalse($record->after->active);
        self::assertSame('Opening.', $record->reason->value);
        self::assertSame('2026-01-01T00:00:00+00:00', $record->at->format(\DATE_ATOM));
    }

    /**
     * A state an operator set for an earlier flag of the same name is kept.
     */
    public function testAStateLeftByAnEarlierFlagIsKept(): void
    {
        $this->states->flags['beta'] = true;

        $this->editor->create($this->request(new FlagDefinition(FlagName::of('beta'), false)));

        self::assertTrue($this->states->flags['beta']);
    }

    public function testANameAnotherProviderDeclaresCannotBeCreated(): void
    {
        $this->expectException(EditConflict::class);
        $this->expectExceptionMessageIsOrContains('A flag named "new-checkout" is already declared.');

        try {
            $this->editor->create($this->request(new FlagDefinition(FlagName::of('new-checkout'), false)));
        } finally {
            self::assertSame([], $this->auditor->edits);
        }
    }

    public function testACreationThatLostARaceIsAConflict(): void
    {
        $definitions = new InMemoryDefinitions();
        $editor = new FlagEditor(new ListAuthorizer(['operator']), $definitions, $this->states, $this->auditor, new FrozenClock(), new FlagCatalog([]));
        $definitions->definitions['beta'] = new RevisedDefinition(new FlagDefinition(FlagName::of('beta'), false), 1);

        $this->expectException(EditConflict::class);

        $editor->create($this->request(new FlagDefinition(FlagName::of('beta'), false)));
    }

    public function testACreationNamesNoRevision(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A creation names no revision');

        $this->editor->create($this->request(new FlagDefinition(FlagName::of('beta'), false), 1));
    }

    public function testARefusedCreationWritesAndAuditsNothing(): void
    {
        $this->expectException(ToggleRefused::class);

        try {
            $this->editor->create(new EditRequest(new FlagDefinition(FlagName::of('beta'), false), 'intern', ToggleReason::of('Trying.')));
        } finally {
            self::assertSame([], $this->journal->calls);
        }
    }

    /**
     * An edit keeps the stored active state, whatever the request says, and increments the revision.
     */
    public function testAnEditKeepsTheActiveStateAndAppliesAgainstItsRevision(): void
    {
        $this->definitions->definitions['beta'] = new RevisedDefinition(new FlagDefinition(FlagName::of('beta'), true), 3);
        $rollout = new FlagDefinition(FlagName::of('beta'), false, [new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(20))]);

        $this->editor->edit($this->request($rollout, 3));

        $stored = $this->definitions->definitions['beta'];
        self::assertSame(4, $stored->revision);
        self::assertTrue($stored->definition->active);
        self::assertCount(1, $stored->definition->rules);
        self::assertSame(['read', 'audit', 'write'], $this->journal->calls);
        self::assertTrue($this->auditor->edits[0]->before?->active);
        self::assertSame([], $this->auditor->edits[0]->before->rules);
    }

    public function testAnEditAgainstAnOlderRevisionIsAConflict(): void
    {
        $this->definitions->definitions['beta'] = new RevisedDefinition(new FlagDefinition(FlagName::of('beta'), true), 3);

        $this->expectException(EditConflict::class);
        $this->expectExceptionMessageIsOrContains('The flag "beta" changed since revision 2');

        try {
            $this->editor->edit($this->request(new FlagDefinition(FlagName::of('beta'), true), 2));
        } finally {
            self::assertSame([], $this->auditor->edits);
        }
    }

    public function testAnEditThatLostTheWriteIsAConflict(): void
    {
        $this->definitions->definitions['beta'] = new RevisedDefinition(new FlagDefinition(FlagName::of('beta'), true), 1);
        $this->definitions->losesWrites = true;

        $this->expectException(EditConflict::class);

        $this->editor->edit($this->request(new FlagDefinition(FlagName::of('beta'), true), 1));
    }

    public function testAnEditOfAFlagTheStoreDoesNotHoldIsUnknown(): void
    {
        $this->expectException(UnknownFlag::class);

        $this->editor->edit($this->request(new FlagDefinition(FlagName::of('new-checkout'), true), 1));
    }

    public function testAnEditNamesItsRevision(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('An edit names the revision it was made against.');

        $this->editor->edit($this->request(new FlagDefinition(FlagName::of('beta'), true)));
    }

    public function testARefusedEditReadsNothing(): void
    {
        $this->expectException(ToggleRefused::class);

        try {
            $this->editor->edit(new EditRequest(new FlagDefinition(FlagName::of('beta'), false), 'intern', ToggleReason::of('Trying.'), 1));
        } finally {
            self::assertSame([], $this->journal->calls);
        }
    }

    private function request(FlagDefinition $definition, ?int $revision = null): EditRequest
    {
        return new EditRequest($definition, 'operator', ToggleReason::of('Opening.'), $revision);
    }
}
