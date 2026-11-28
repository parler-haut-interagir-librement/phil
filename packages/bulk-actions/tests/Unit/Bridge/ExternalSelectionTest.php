<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Bridge;

use Phil\BulkActions\Bridge\ExternalSelection;
use Phil\BulkActions\Contract\ItemRepository;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Exception\UnknownAction;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;
use Phil\BulkActions\Tests\Support\Fixture;
use Phil\BulkActions\Tests\Support\MemoryProducts;
use Phil\BulkActions\Tests\Support\OtherItem;
use Phil\BulkActions\Tests\Support\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExternalSelection::class)]
final class ExternalSelectionTest extends TestCase
{
    public function testTheRowsOfADataTableKeepTheirVersions(): void
    {
        $bridge = new ExternalSelection(new Fixture()->targets);

        self::assertEquals(
            Selection::identifiers(['p1', 'p2'], ['p1' => '3', 'p2' => '1']),
            $bridge->fromRows('products', [new Product('p1', 'Mug', version: 3), new Product('p2', 'Cap')]),
        );
    }

    public function testARowWithoutVersionIsSelectedWithoutOne(): void
    {
        $fixture = new Fixture();
        $repository = new readonly class($fixture->products) implements ItemRepository {
            public function __construct(private MemoryProducts $inner)
            {
            }

            public function find(array $identifiers): array
            {
                return $this->inner->find($identifiers);
            }

            public function supports(FilterCriteria $filter): bool
            {
                return false;
            }

            public function count(FilterCriteria $filter, array $excluded): int
            {
                return 0;
            }

            public function matching(FilterCriteria $filter, array $excluded, ?string $after, int $limit): array
            {
                return [];
            }

            public function identify(object $item): string
            {
                return $this->inner->identify($item);
            }

            public function version(object $item): ?string
            {
                return null;
            }
        };
        $bridge = new ExternalSelection(new Targets([new Target('products', Product::class, $repository, [])]));

        self::assertSame([], $bridge->fromRows('products', [new Product('p1', 'Mug')])->versions);
    }

    public function testARowOfAnotherKindIsRefused(): void
    {
        $bridge = new ExternalSelection(new Fixture()->targets);

        foreach ([new OtherItem(), ['id' => 'p1']] as $row) {
            try {
                $bridge->fromRows('products', [new Product('p1', 'Mug'), $row]);
                self::fail('A row of another kind was selected.');
            } catch (InvalidInput $exception) {
                self::assertSame('The row must be an item of target "products".', $exception->getMessage());
            }
        }

        $this->expectExceptionObject(UnknownAction::target('orders'));
        $bridge->fromRows('orders', []);
    }

    public function testABatchActionsClassIsComparedWithTheTargets(): void
    {
        $bridge = new ExternalSelection(new Fixture()->targets);

        self::assertEquals(Selection::identifiers(['7', 'p1']), $bridge->fromBatch('products', Product::class, [7, 'p1']));

        $this->expectExceptionObject(InvalidInput::because('class of the selection', 'must be the class of target "products"'));
        $bridge->fromBatch('products', OtherItem::class, [7]);
    }
}
