<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Application;

use Phil\DataTableBundle\Application\Contract\DataSource;
use Phil\DataTableBundle\Application\Contract\DataTable;
use Phil\DataTableBundle\Application\Definition\Action;
use Phil\DataTableBundle\Application\Definition\Column;
use Phil\DataTableBundle\Application\Definition\Editing;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\EditOutcome;
use Phil\DataTableBundle\Application\EditStart;
use Phil\DataTableBundle\Application\EditStatus;
use Phil\DataTableBundle\Application\Exception\AccessDenied;
use Phil\DataTableBundle\Application\Exception\ActionRefused;
use Phil\DataTableBundle\Application\Exception\EditConflict;
use Phil\DataTableBundle\Application\Exception\NotFound;
use Phil\DataTableBundle\Application\OpenTable;
use Phil\DataTableBundle\Application\Query\ResultPage;
use Phil\DataTableBundle\Application\Query\TableQuery;
use Phil\DataTableBundle\Application\Source\InMemoryDataSource;
use Phil\DataTableBundle\Application\TableOperations;
use Phil\DataTableBundle\Application\Tables;
use Phil\DataTableBundle\Tests\Fixtures\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;

#[CoversClass(TableOperations::class)]
#[CoversClass(EditOutcome::class)]
#[CoversClass(EditStart::class)]
#[CoversClass(OpenTable::class)]
#[CoversClass(Tables::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(AccessDenied::class)]
final class TableOperationsTest extends TestCase
{
    private Kit $kit;

    /** @var list<list<array<string, mixed>|object>> */
    private array $performed = [];

    /** @var list<mixed> */
    private array $applied = [];

    protected function setUp(): void
    {
        $this->kit = new Kit();
    }

    public function testAnActionGetsItsRowsAndAsksEachOfThem(): void
    {
        $this->kit->operations->perform($this->table(), 'both', ['2', '1', '2']);

        self::assertSame([[['id' => 1, 'title' => 'One', 'version' => 1], ['id' => 2, 'title' => 'Two', 'version' => 1]]], $this->performed);
        self::assertSame([['BOTH', ['id' => 1, 'title' => 'One', 'version' => 1]], ['BOTH', ['id' => 2, 'title' => 'Two', 'version' => 1]]], $this->kit->access->asked);
    }

    public function testAuthorizingSaysWhatWouldRun(): void
    {
        [$action, $rows] = $this->kit->operations->authorize($this->table(), 'one', ['2']);

        self::assertSame(['one', [['id' => 2, 'title' => 'Two', 'version' => 1]], []], [$action->name, $rows, $this->performed]);
    }

    public function testIdentifiersReachTheSourceOnceAndInOrder(): void
    {
        $source = new class implements DataSource {
            /** @var list<list<string>> */
            public array $asked = [];

            public function fetch(TableQuery $query): ResultPage
            {
                return new ResultPage([], 0);
            }

            public function find(array $ids): array
            {
                $this->asked[] = $ids;

                return array_map(static fn (string $id): array => ['id' => $id], $ids);
            }
        };

        $this->kit->operations->perform(new OpenTable($this->table()->definition, $source), 'both', ['a', 'a', 'b']);

        self::assertSame([['a', 'b']], $source->asked);
    }

    public function testAVoterStopsTheWholeCall(): void
    {
        $this->kit->access->refused = ['BOTH'];

        try {
            $this->kit->operations->perform($this->table(), 'both', ['1', '2']);
            self::fail('The action ran.');
        } catch (AccessDenied $accessDenied) {
            self::assertSame('Access to the action "both" is denied: the attribute "BOTH" is not granted.', $accessDenied->getMessage());
            self::assertSame(403, $accessDenied->getStatusCode());
        }

        self::assertSame([], $this->performed);
    }

    public function testAnActionOutsideTheDeclarationIsNotFound(): void
    {
        foreach ([['nope', ['1']], ['one', ['1', '2']], ['both', []], ['both', array_map(strval(...), range(1, 501))]] as [$name, $ids]) {
            try {
                $this->kit->operations->perform($this->table(), $name, $ids);
                self::fail('The action ran.');
            } catch (NotFound $notFound) {
                self::assertSame(\sprintf('The table "t" has no action "%s" for these rows.', $name), $notFound->getMessage());
            }
        }

        self::assertSame([], $this->performed);
    }

    public function testASelectionOfTheMostRowsOfAPageIsAccepted(): void
    {
        $rows = array_map(static fn (int $id): array => ['id' => $id], range(1, TableBuilder::MAX_PAGE_SIZE));
        $table = new OpenTable($this->table()->definition, new InMemoryDataSource($rows));

        $this->kit->operations->perform($table, 'both', array_map(strval(...), range(1, TableBuilder::MAX_PAGE_SIZE)));

        self::assertCount(TableBuilder::MAX_PAGE_SIZE, $this->performed[0]);
    }

    public function testARowOutsideTheSourceIsNotFound(): void
    {
        $this->expectException(NotFound::class);
        $this->expectExceptionMessageIsOrContains('The table "t" does not hold every row asked for.');

        $this->kit->operations->perform($this->table(), 'both', ['1', '99']);
    }

    public function testAnEditStartsFromTheRow(): void
    {
        $start = $this->kit->operations->start($this->table(), '1', 'title');

        self::assertSame(['One', '1'], [$start->value, $start->version]);
        self::assertNull($this->kit->operations->start($this->table(null), '1', 'title')->version);
    }

    public function testAnEditIsTypedValidatedAndApplied(): void
    {
        $outcome = $this->kit->operations->edit($this->table(), '1', 'title', 'New', '1');

        self::assertTrue($outcome->isSaved());
        self::assertSame([EditStatus::Saved, []], [$outcome->status, $outcome->errors]);
        self::assertSame([[['id' => 1, 'title' => 'One', 'version' => 1], 'New']], $this->applied);
        self::assertSame(['EDIT', ['id' => 1, 'title' => 'One', 'version' => 1]], $this->kit->access->asked[0]);
    }

    public function testAnInvalidValueIsNotApplied(): void
    {
        $outcome = $this->kit->operations->edit($this->table(), '1', 'title', 'Too long', '1');

        self::assertSame(EditStatus::Invalid, $outcome->status);
        self::assertFalse($outcome->isSaved());
        self::assertStringContainsString('too long', $outcome->errors[0]);
        self::assertSame([], $this->applied);
    }

    public function testAValueThatCannotBeReadIsNotApplied(): void
    {
        $outcome = $this->kit->operations->edit($this->table(), '1', 'number', 'abc', '1');

        self::assertSame([EditStatus::Invalid, ['table.edit.invalid']], [$outcome->status, $outcome->errors]);
        self::assertSame([], $this->applied);
    }

    public function testAnOlderVersionIsAConflict(): void
    {
        foreach (['2', null, ''] as $version) {
            $outcome = $this->kit->operations->edit($this->table(), '1', 'title', 'New', $version);
            self::assertSame([EditStatus::Conflict, ['table.edit.conflict']], [$outcome->status, $outcome->errors]);
        }

        self::assertSame([], $this->applied);
    }

    public function testATableWithoutVersionIsNotCompared(): void
    {
        self::assertTrue($this->kit->operations->edit($this->table(null), '1', 'title', 'New', null)->isSaved());
    }

    public function testTheApplyingMaySayItConflictsOrRefuses(): void
    {
        $conflict = $this->kit->operations->edit($this->table(), '1', 'conflicts', 'x', '1');
        self::assertSame([EditStatus::Conflict, ['table.edit.conflict']], [$conflict->status, $conflict->errors]);

        $refused = $this->kit->operations->edit($this->table(), '1', 'refuses', 'x', '1');
        self::assertSame([EditStatus::Invalid, ['No, not that.']], [$refused->status, $refused->errors]);
    }

    public function testACellOutsideTheDeclarationIsNotFound(): void
    {
        foreach (['nope', 'plain'] as $column) {
            try {
                $this->kit->operations->edit($this->table(), '1', $column, 'x', '1');
                self::fail('The edit ran.');
            } catch (NotFound $notFound) {
                self::assertSame(\sprintf('The table "t" has no editable column "%s".', $column), $notFound->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('does not hold every row');
        $this->kit->operations->edit($this->table(), '99', 'title', 'x', '1');
    }

    public function testAnEditIsAskedOfTheVoter(): void
    {
        $this->kit->access->refused = ['EDIT'];
        $this->expectException(AccessDenied::class);
        $this->expectExceptionMessageIsOrContains('Access to the column "title" is denied: the attribute "EDIT" is not granted.');

        $this->kit->operations->edit($this->table(), '1', 'title', 'x', '1');
    }

    public function testATableIsOpenedForTheViewer(): void
    {
        $source = new InMemoryDataSource([]);
        $table = new class($source) implements DataTable {
            /** @var array<string, scalar|null>|null */
            public ?array $context = null;

            public function __construct(private readonly DataSource $source)
            {
            }

            public static function name(): string
            {
                return 'open';
            }

            public function configure(TableBuilder $table): void
            {
                $table->caption('C')->column(Column::text('a', 'A'))->requires('VIEW');
            }

            public function source(array $context): DataSource
            {
                $this->context = $context;

                return $this->source;
            }
        };
        $tables = $this->kit->tables($table);
        $open = $tables->open('open', ['a' => 1]);

        self::assertSame(['open', $source, ['a' => 1]], [$open->definition->name, $open->source, $table->context]);
        self::assertSame([['VIEW', null]], $this->kit->access->asked);

        $this->kit->access->refused = ['VIEW'];

        try {
            $tables->open('open');
            self::fail('The table opened.');
        } catch (AccessDenied $accessDenied) {
            self::assertSame('Access to the table "open" is denied: the attribute "VIEW" is not granted.', $accessDenied->getMessage());
        }

        $this->expectException(NotFound::class);
        $this->expectExceptionMessageIsOrContains('There is no data table named "nope".');
        $tables->open('nope');
    }

    private function table(?string $version = 'version'): OpenTable
    {
        $apply = function (array|object $row, mixed $value): void {
            $this->applied[] = [$row, $value];
        };
        $builder = new TableBuilder('t')
            ->caption('C')
            ->column(Column::text('title', 'T')->editable(new Editing('EDIT', $apply, [new Length(max: 5)])))
            ->column(Column::number('number', 'N')->editable(new Editing('EDIT', $apply)))
            ->column(Column::text('conflicts', 'X')->editable(new Editing('EDIT', static function (): never {
                throw new EditConflict();
            })))
            ->column(Column::text('refuses', 'X')->editable(new Editing('EDIT', static function (): never {
                throw new ActionRefused('No, not that.');
            })))
            ->column(Column::text('plain', 'P'))
            ->action(Action::row('one', 'One', 'ONE', $this->record(...)))
            ->action(Action::bulk('both', 'Both', 'BOTH', $this->record(...)));

        if (null !== $version) {
            $builder->version($version);
        }

        return new OpenTable($builder->build(), new InMemoryDataSource([['id' => 1, 'title' => 'One', 'version' => 1], ['id' => 2, 'title' => 'Two', 'version' => 1]]));
    }

    /**
     * @param list<array<string, mixed>|object> $rows
     */
    private function record(array $rows): void
    {
        $this->performed[] = $rows;
    }
}
