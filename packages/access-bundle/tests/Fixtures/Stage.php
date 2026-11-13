<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Fixtures;

use DateInterval;
use Phil\AccessBundle\Application\AccessGroups;
use Phil\AccessBundle\Application\AccessModel;
use Phil\AccessBundle\Application\AccessSubject;
use Phil\AccessBundle\Application\ItemAccess;
use Phil\AccessBundle\Application\Items;
use Phil\AccessBundle\Application\Sharing;
use Phil\AccessBundle\Application\Spaces;
use Phil\AccessBundle\Application\ViewAs;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationWriter;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Clock\MockClock;

/**
 * The bundle's application services on an in-memory store, a mock clock, a member a test signs in and a log kept in
 * memory: pages and documents, the default sharing levels, the space `main`, and two access groups — `supporters`, by
 * purchase or invitation for a month, and `newcomers`, by default.
 */
final class Stage extends AbstractLogger
{
    public MockClock $clock;

    public InMemoryTupleStore $store;

    public Model $model;

    public SharingLevels $levels;

    public RelationChecker $checker;

    public RelationWriter $writer;

    public FixedMember $member;

    public MemoryViewAs $viewAsStore;

    public RecordingEvents $events;

    public Spaces $spaces;

    public ViewAs $viewAs;

    public AccessSubject $subject;

    public ItemAccess $access;

    public Items $items;

    public AccessGroups $groups;

    public Sharing $sharing;

    /** @var list<array{string, string}> */
    public array $logs = [];

    public function __construct(?Budget $budget = null)
    {
        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->store = new InMemoryTupleStore($this->clock);
        $this->levels = SharingLevels::of(SharingLevels::DEFAULT);
        $this->model = AccessModel::create(['page', 'document'], $this->levels);
        $this->checker = new RelationChecker($this->model, $this->store, $budget ?? new Budget());
        $this->writer = new RelationWriter($this->model, $this->store);
        $this->member = new FixedMember();
        $this->viewAsStore = new MemoryViewAs();
        $this->events = new RecordingEvents();
        $this->spaces = new Spaces($this->writer, $this->checker, 'main');
        $this->viewAs = new ViewAs($this->viewAsStore, $this->member, $this->spaces, $this, $this->events);
        $this->subject = new AccessSubject($this->member, $this->viewAs);
        $this->access = new ItemAccess($this->checker, $this->subject, $this->levels, $this);
        $this->items = new Items($this->writer, $this->store, $this->levels, $this->spaces);
        $this->groups = new AccessGroups($this->writer, $this->store, $this->clock, [
            AccessGroup::of('supporters', [EntryPoint::Purchase, EntryPoint::Invitation], new DateInterval('P1M')),
            AccessGroup::of('newcomers', [EntryPoint::Default]),
        ], $this->events);
        $this->sharing = new Sharing($this->writer, $this->store, $this->levels, $this->groups, $this->clock);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[\Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $replace = [];

        foreach ($context as $key => $value) {
            $replace['{'.$key.'}'] = \is_scalar($value) ? (string) $value : '';
        }

        $this->logs[] = [\is_string($level) ? $level : '', strtr((string) $message, $replace)];
    }
}
