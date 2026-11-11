<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuditor;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRecord;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRecord;
use RuntimeException;

final class RecordingAuditor implements ToggleAuditor
{
    /** @var list<ToggleRecord> */
    public array $records = [];

    /** @var list<EditRecord> */
    public array $edits = [];

    public bool $failing = false;

    /**
     * Run before a toggle is recorded: what another process does meanwhile.
     *
     * @var (Closure(): void)|null
     */
    public ?Closure $meanwhile = null;

    public function __construct(
        private readonly CallJournal $journal = new CallJournal(),
    ) {
    }

    #[Override]
    public function record(ToggleRecord $record): void
    {
        $this->journal->add('audit');

        if ($this->failing) {
            throw new RuntimeException('The audit trail is unavailable.');
        }

        if ($this->meanwhile instanceof Closure) {
            ($this->meanwhile)();
        }

        $this->records[] = $record;
    }

    #[Override]
    public function recordEdit(EditRecord $record): void
    {
        $this->journal->add('audit');

        if ($this->failing) {
            throw new RuntimeException('The audit trail is unavailable.');
        }

        $this->edits[] = $record;
    }
}
