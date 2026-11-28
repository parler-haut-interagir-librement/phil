<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit;

use DateTimeImmutable;
use Phil\BulkActions\BulkPreviewer;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Exception\AccessDenied;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Exception\SelectionUnavailable;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Preview\Preview;
use Phil\BulkActions\Preview\SampleItem;
use Phil\BulkActions\Preview\ValueChange;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionToken;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Tests\Support\Fixture;
use Phil\BulkActions\Tests\Support\OtherItem;
use Phil\BulkActions\Tests\Support\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BulkPreviewer::class)]
#[CoversClass(Guard::class)]
#[CoversClass(Preview::class)]
#[CoversClass(SampleItem::class)]
#[CoversClass(ValueChange::class)]
#[CoversClass(SelectionUnavailable::class)]
#[CoversClass(InvalidInput::class)]
#[CoversClass(AccessDenied::class)]
final class BulkPreviewerTest extends TestCase
{
    private Fixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new Fixture();
        $this->fixture->products->add(
            new Product('p1', 'Mug', 'draft', 1200),
            new Product('p2', 'Cap', 'live'),
            new Product('p3', 'Tee', 'draft', 0),
            new Product('p4', 'Bag', 'draft', 900),
        );
    }

    public function testItShowsTheOldAndNewValuesOfASample(): void
    {
        $this->fixture->authorizer->deniedItems = ['p3'];
        $token = $this->select(Selection::identifiers(['p1', 'gone', 'p2', 'p3', 'p4']));
        $preview = $this->fixture->previewer->preview('ana', $token, ChangeSet::of(['status' => 'live', 'price' => null]), Fixture::now());

        self::assertSame('products', $preview->target);
        self::assertSame('edit', $preview->action);
        self::assertSame(5, $preview->count);
        self::assertFalse($preview->destructive);
        self::assertEquals(Fixture::now()->modify('+900 seconds'), $preview->expiresAt);
        self::assertSame(['status' => 'live', 'price' => null], $preview->changes->values);
        self::assertEquals([
            new SampleItem('p1', 'Mug', true, [new ValueChange('status', 'draft', 'live'), new ValueChange('price', 1200, null)]),
            new SampleItem('p2', 'Cap', true, [new ValueChange('status', 'live', 'live'), new ValueChange('price', null, null)]),
        ], $preview->sample);
        self::assertSame(0, $preview->deniedInSample());
        self::assertTrue($preview->sample[0]->changes[0]->changes());
        self::assertFalse($preview->sample[1]->changes[0]->changes());
        self::assertFalse($preview->sample[1]->changes[1]->changes());
        self::assertSame([['p1', 'gone', 'p2']], $this->fixture->products->finds);
        self::assertNotNull($this->fixture->selections->peek($token->fingerprint()), 'A preview does not use the token.');
    }

    public function testTheSampleSaysWhichItemsTheVoterRefuses(): void
    {
        $this->fixture->authorizer->deniedItems = ['p3'];
        $token = $this->select(Selection::filter(FilterCriteria::of('status', ['status' => 'draft']), ['p1']));
        $preview = $this->fixture->previewer->preview('ana', $token, ChangeSet::of(['price' => 0]), Fixture::now());

        self::assertSame(2, $preview->count);
        self::assertSame(['p3', 'p4'], array_map(static fn (SampleItem $item): string => $item->item, $preview->sample));
        self::assertFalse($preview->sample[0]->granted);
        self::assertTrue($preview->sample[1]->granted);
        self::assertSame(1, $preview->deniedInSample());
        self::assertFalse($preview->sample[0]->changes[0]->changes(), '0 stays 0.');
        self::assertContains('apply:ana:PRODUCT_EDIT:p3', $this->fixture->authorizer->asked);
    }

    public function testAnItemOfAnotherClassIsLeftOutOfTheSample(): void
    {
        $this->fixture->products->impostors['p2'] = new OtherItem();
        $token = $this->select(Selection::identifiers(['p1', 'p2']));

        self::assertSame(['p1'], array_map(static fn (SampleItem $item): string => $item->item, $this->fixture->previewer->preview('ana', $token, ChangeSet::of(['price' => 1]), Fixture::now())->sample));
    }

    public function testADestructiveActionWithoutFieldsIsPreviewedWithoutChanges(): void
    {
        $token = $this->select(Selection::identifiers(['p1']), 'archive');
        $preview = $this->fixture->previewer->preview('ana', $token, ChangeSet::none(), Fixture::now());

        self::assertTrue($preview->destructive);
        self::assertEquals([new SampleItem('p1', 'Mug', true, [])], $preview->sample);
    }

    public function testASampleOfZeroReadsNothing(): void
    {
        $fixture = new Fixture(new Limits(sampleSize: 0));
        $fixture->products->seed(2);

        $token = $fixture->selector->select('ana', 'products', 'edit', Selection::filter(FilterCriteria::of('status', ['status' => 'draft'])), Fixture::now());

        $preview = $fixture->previewer->preview('ana', $token, ChangeSet::of(['price' => 1]), Fixture::now());

        self::assertSame(2, $preview->count);
        self::assertSame([], $preview->sample);
        self::assertSame([], $fixture->products->finds);
    }

    public function testAFieldTheActionDoesNotSetIsRefused(): void
    {
        $token = $this->select(Selection::identifiers(['p1']));

        $this->expectExceptionObject(InvalidInput::because('field "name"', 'must be one the action "edit" sets'));
        $this->fixture->previewer->preview('ana', $token, ChangeSet::of(['price' => 1, 'name' => 'x']), Fixture::now());
    }

    public function testAnEditWithoutFieldIsRefused(): void
    {
        $token = $this->select(Selection::identifiers(['p1']));

        $this->expectExceptionObject(InvalidInput::because('change', 'must set at least one field'));
        $this->fixture->previewer->preview('ana', $token, ChangeSet::none(), Fixture::now());
    }

    public function testAnActionWithoutFieldsTakesNoChange(): void
    {
        $token = $this->select(Selection::identifiers(['p1']), 'archive');

        $this->expectExceptionObject(InvalidInput::because('field "status"', 'must be one the action "archive" sets'));
        $this->fixture->previewer->preview('ana', $token, ChangeSet::of(['status' => 'x']), Fixture::now());
    }

    public function testTheRightIsAskedAgain(): void
    {
        $token = $this->select(Selection::identifiers(['p1']));
        $this->fixture->authorizer->runners = [];

        $this->expectExceptionObject(AccessDenied::toRun('products', 'edit'));
        $this->fixture->previewer->preview('ana', $token, ChangeSet::of(['price' => 1]), Fixture::now());
    }

    public function testAnotherPersonCannotPreviewTheSelection(): void
    {
        $this->fixture->authorizer->runners = ['ana', 'bob'];
        $token = $this->select(Selection::identifiers(['p1']));

        $this->expectExceptionObject(SelectionUnavailable::create());
        $this->fixture->previewer->preview('bob', $token, ChangeSet::of(['price' => 1]), Fixture::now());
    }

    public function testAnExpiredSelectionCannotBePreviewed(): void
    {
        $token = $this->select(Selection::identifiers(['p1']));

        $this->expectExceptionObject(SelectionUnavailable::create());
        $this->fixture->previewer->preview('ana', $token, ChangeSet::of(['price' => 1]), new DateTimeImmutable('2026-11-21 10:15:00+00:00'));
    }

    public function testAnUnknownTokenCannotBePreviewed(): void
    {
        $this->expectExceptionObject(SelectionUnavailable::create());
        $this->fixture->previewer->preview('ana', SelectionToken::generate(), ChangeSet::of(['price' => 1]), Fixture::now());
    }

    private function select(Selection $selection, string $action = 'edit'): SelectionToken
    {
        return $this->fixture->selector->select('ana', 'products', $action, $selection, Fixture::now());
    }
}
