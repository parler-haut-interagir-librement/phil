<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\AccountDeletion;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Override;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\AccountDeletion\DeletionWorkflow;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ErasureWorkbench;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedExporter;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryAccountDeletions;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryExportArchive;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(AccountDeletions::class)]
final class AccountDeletionWorkflowTest extends TestCase
{
    private ErasureWorkbench $bench;

    private MemoryAccountDeletions $store;

    private RecordingEraser $members;

    private RecordingEraser $forum;

    private ErasureRequests $workflow;

    private TestHandler $log;

    #[Override]
    protected function setUp(): void
    {
        $this->bench = new ErasureWorkbench();
        $this->store = new MemoryAccountDeletions();
        $this->members = new RecordingEraser('members');
        $this->forum = new RecordingEraser('forum');
        $this->workflow = new ErasureRequests($this->bench->store, $this->bench->outbox, [$this->members, $this->forum], [], $this->bench->clock);
        $this->log = new TestHandler();
    }

    public function testTheErasureStageOpensOneRequestAndClosesTheDeletionOnceItCompleted(): void
    {
        $deletions = $this->deletions();
        $deletion = $deletions->request(DataSubject::fromString('member-1'));
        $this->bench->clock->sleep(31 * 86_400);

        self::assertSame(DeletionPlace::Exported, $deletions->advance($deletion->id));
        self::assertSame(DeletionPlace::Exported, $deletions->advance($deletion->id));

        $erasure = $this->workflow->find($deletion->id);
        self::assertSame(RequestState::Running, $erasure?->state);
        self::assertTrue($erasure->isAbout(DataSubject::fromString('member-1')));
        self::assertCount(2, $this->bench->pending(), 'The request is opened once, under the identifier of the deletion.');
        self::assertSame(0, $this->members->calls, 'The modules run in the workers, not in the sweep.');
        self::assertTrue($this->log->hasInfo('Account deletion {id}: its erasure request is {state}.'));

        $this->workflow->run($deletion->id, 'members');
        $this->workflow->run($deletion->id, 'forum');

        self::assertSame(DeletionPlace::Erased, $deletions->advance($deletion->id));
        self::assertNotNull($this->store->rows[$deletion->id]->closedAt());
        self::assertSame(1, $this->members->calls);
    }

    public function testADeletionWhoseErasureFailedStaysForTheOperator(): void
    {
        $deletions = $this->deletions();
        $deletion = $deletions->request(DataSubject::fromString('member-1'));
        $this->bench->clock->sleep(31 * 86_400);
        $deletions->advance($deletion->id);

        $this->workflow->fail($deletion->id);

        self::assertSame(DeletionPlace::Exported, $deletions->advance($deletion->id));
    }

    private function deletions(): AccountDeletions
    {
        return new AccountDeletions(
            $this->store,
            DeletionWorkflow::create(),
            new LockFactory(new InMemoryStore()),
            new SubjectExport([new FixedExporter('members', [])]),
            new SubjectErasure([$this->members, $this->forum]),
            new MemoryExportArchive(),
            $this->bench->clock,
            'P30D',
            'P7D',
            new Logger('privacy', [$this->log]),
            null,
            $this->workflow,
        );
    }
}
