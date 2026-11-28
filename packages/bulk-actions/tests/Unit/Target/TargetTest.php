<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Target;

use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Contract\BulkAction;
use Phil\BulkActions\Contract\ItemRepository;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Exception\UnknownAction;
use Phil\BulkActions\Internal\Check;
use Phil\BulkActions\Run\ItemContext;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Target\Target;
use Phil\BulkActions\Target\Targets;
use Phil\BulkActions\Tests\Support\ArchiveProducts;
use Phil\BulkActions\Tests\Support\EditProducts;
use Phil\BulkActions\Tests\Support\MemoryProducts;
use Phil\BulkActions\Tests\Support\OtherItem;
use Phil\BulkActions\Tests\Support\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Target::class)]
#[CoversClass(Targets::class)]
#[CoversClass(Limits::class)]
#[CoversClass(UnknownAction::class)]
#[CoversClass(InvalidInput::class)]
#[CoversClass(Check::class)]
final class TargetTest extends TestCase
{
    public function testATargetFindsItsActionsByName(): void
    {
        $edit = new EditProducts();
        $archive = new ArchiveProducts();
        $products = new MemoryProducts();
        $target = new Target('products', Product::class, $products, [$edit, $archive]);

        self::assertSame('products', $target->name);
        self::assertSame(Product::class, $target->class);
        self::assertSame($products, $target->items);
        self::assertSame($edit, $target->action('edit'));
        self::assertSame($archive, $target->action('archive'));
        self::assertSame([$edit, $archive], $target->actions());
        self::assertTrue($target->holds(new Product('p1', 'Mug')));
        self::assertFalse($target->holds(new OtherItem()));
    }

    public function testAnInterfaceIsATargetClass(): void
    {
        self::assertSame(ItemRepository::class, new Target('repositories', ItemRepository::class, new MemoryProducts(), [])->class);
        self::assertTrue(new Target('repositories', ItemRepository::class, new MemoryProducts(), [])->holds(new MemoryProducts()));
    }

    public function testAnUnknownActionIsRefused(): void
    {
        $this->expectExceptionObject(UnknownAction::action('products', 'delete'));

        new Target('products', Product::class, new MemoryProducts(), [new EditProducts()])->action('delete');
    }

    public function testAnActionIsDeclaredOnce(): void
    {
        $this->expectExceptionObject(InvalidInput::because('action "edit" of target "products"', 'must be declared once'));

        new Target('products', Product::class, new MemoryProducts(), [new EditProducts(), new EditProducts()]);
    }

    public function testTheClassMustExist(): void
    {
        $this->expectExceptionObject(InvalidInput::because('class of target "products"', 'must exist'));

        /* @phpstan-ignore argument.type (the class a faulty configuration names) */
        new Target('products', 'App\Missing', new MemoryProducts(), []);
    }

    public function testTheFieldsOfAnActionAreChecked(): void
    {
        $edit = new EditProducts();
        $edit->fieldList = ['status', '1price'];

        $this->expectExceptionObject(InvalidInput::because('field of action "edit"', 'must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most'));

        new Target('products', Product::class, new MemoryProducts(), [$edit]);
    }

    public function testAnActionNameIsChecked(): void
    {
        $this->expectExceptionObject(InvalidInput::because('action', 'must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most'));

        new Target('products', Product::class, new MemoryProducts(), [new class implements BulkAction {
            public function name(): string
            {
                return 'bad name';
            }

            public function attribute(): string
            {
                return 'X';
            }

            public function isDestructive(): bool
            {
                return false;
            }

            public function fields(): array
            {
                return [];
            }

            public function label(object $item): string
            {
                return '';
            }

            public function values(object $item): array
            {
                return [];
            }

            public function apply(object $item, ChangeSet $changes, ItemContext $context): void
            {
            }
        }]);
    }

    public function testTheNameIsChecked(): void
    {
        $this->expectExceptionObject(InvalidInput::because('target', 'must be a letter followed by letters, digits, "_", "-" or ".", 64 bytes at most'));

        new Target('my products', Product::class, new MemoryProducts(), []);
    }

    public function testTargetsAreFoundByName(): void
    {
        $products = new Target('products', Product::class, new MemoryProducts(), []);
        $targets = new Targets([$products]);

        self::assertSame($products, $targets->get('products'));
        self::assertSame($products, $targets->forClass('products', Product::class));

        try {
            $targets->get('Products');
            self::fail('A target was found under another name.');
        } catch (UnknownAction $unknownAction) {
            self::assertEquals(UnknownAction::target('Products'), $unknownAction);
        }

        $this->expectExceptionObject(InvalidInput::because('class of the selection', 'must be the class of target "products"'));
        $targets->forClass('products', OtherItem::class);
    }

    public function testATargetIsDeclaredOnce(): void
    {
        $this->expectExceptionObject(InvalidInput::because('target "products"', 'must be declared once'));

        new Targets([new Target('products', Product::class, new MemoryProducts(), []), new Target('products', Product::class, new MemoryProducts(), [])]);
    }

    public function testTheLimitsHaveDefaults(): void
    {
        $limits = new Limits();

        self::assertSame(1_000, $limits->maxIdentifiers);
        self::assertSame(10_000, $limits->maxItems);
        self::assertSame(100, $limits->chunkSize);
        self::assertSame(10, $limits->sampleSize);
        self::assertSame(900, $limits->lifetimeSeconds);
    }

    public function testTheLimitsAcceptTheirBounds(): void
    {
        $low = new Limits(1, 1, 1, 0, 60);
        $high = new Limits(Selection::MAX_IDENTIFIERS, 1_000_000, 1_000, 100, 86_400);

        self::assertSame([1, 1, 1, 0, 60], [$low->maxIdentifiers, $low->maxItems, $low->chunkSize, $low->sampleSize, $low->lifetimeSeconds]);
        self::assertSame([10_000, 1_000_000, 1_000, 100, 86_400], [$high->maxIdentifiers, $high->maxItems, $high->chunkSize, $high->sampleSize, $high->lifetimeSeconds]);
    }

    /**
     * @return iterable<string, array{array<string, int>, string}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'no identifier' => [['maxIdentifiers' => 0], 'The maxIdentifiers must be a whole number from 1 to 10000.'];
        yield 'too many identifiers' => [['maxIdentifiers' => 10_001], 'The maxIdentifiers must be a whole number from 1 to 10000.'];
        yield 'no item' => [['maxItems' => 0], 'The maxItems must be a whole number from 1 to 1000000.'];
        yield 'too many items' => [['maxItems' => 1_000_001], 'The maxItems must be a whole number from 1 to 1000000.'];
        yield 'an empty chunk' => [['chunkSize' => 0], 'The chunkSize must be a whole number from 1 to 1000.'];
        yield 'a chunk too large' => [['chunkSize' => 1_001], 'The chunkSize must be a whole number from 1 to 1000.'];
        yield 'a negative sample' => [['sampleSize' => -1], 'The sampleSize must be a whole number from 0 to 100.'];
        yield 'a sample too large' => [['sampleSize' => 101], 'The sampleSize must be a whole number from 0 to 100.'];
        yield 'a lifetime too short' => [['lifetimeSeconds' => 59], 'The lifetimeSeconds must be a whole number from 60 to 86400.'];
        yield 'a lifetime too long' => [['lifetimeSeconds' => 86_401], 'The lifetimeSeconds must be a whole number from 60 to 86400.'];
    }

    /**
     * @param array<string, int> $arguments
     */
    #[DataProvider('outOfBounds')]
    public function testTheLimitsAreBounded(array $arguments, string $message): void
    {
        $this->expectException(InvalidInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Limits(...$arguments);
    }
}
