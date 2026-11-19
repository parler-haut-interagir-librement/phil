<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Tests\Unit;

use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\Filter;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\PlannedItem;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Filter::class)]
final class FilterTest extends TestCase
{
    public function testAnEmptyFilterAcceptsEveryItem(): void
    {
        $filter = Filter::all();

        self::assertTrue($filter->isEmpty());
        self::assertTrue($filter->accepts($this->item('u', null)));
        self::assertTrue($filter->accepts($this->item('v', 'news')->transitionTo(ItemState::Withdrawn)));
        self::assertTrue($filter->byAuthors()->bySections()->byStates()->isEmpty());
    }

    public function testItemsAreFilteredByAuthor(): void
    {
        $filter = Filter::all()->byAuthors(new AuthorId('u'), new AuthorId('w'));

        self::assertFalse($filter->isEmpty());
        self::assertTrue($filter->accepts($this->item('u', null)));
        self::assertTrue($filter->accepts($this->item('w', null)));
        self::assertFalse($filter->accepts($this->item('v', null)));
    }

    public function testItemsAreFilteredBySection(): void
    {
        $filter = Filter::all()->bySections('news', '12');

        self::assertFalse($filter->isEmpty());
        self::assertTrue($filter->accepts($this->item('u', 'news')));
        self::assertTrue($filter->accepts($this->item('u', '12')));
        self::assertFalse($filter->accepts($this->item('u', 'blog')));
        self::assertFalse($filter->accepts($this->item('u', null)));
    }

    public function testItemsAreFilteredByState(): void
    {
        $filter = Filter::all()->byStates(ItemState::Drafting, ItemState::Ready);

        self::assertFalse($filter->isEmpty());
        self::assertTrue($filter->accepts($this->item('u', null)->transitionTo(ItemState::Drafting)));
        self::assertTrue($filter->accepts($this->item('u', null)->transitionTo(ItemState::Drafting)->transitionTo(ItemState::Ready)));
        self::assertFalse($filter->accepts($this->item('u', null)));
    }

    public function testEveryCriterionMustPass(): void
    {
        $filter = Filter::all()->byAuthors(new AuthorId('u'))->bySections('news')->byStates(ItemState::Idea);

        self::assertTrue($filter->accepts($this->item('u', 'news')));
        self::assertFalse($filter->accepts($this->item('v', 'news')));
        self::assertFalse($filter->accepts($this->item('u', 'blog')));
        self::assertFalse($filter->accepts($this->item('u', 'news')->transitionTo(ItemState::Drafting)));
    }

    public function testEachCriterionReplacesItsOwnOnly(): void
    {
        $filter = Filter::all()->byAuthors(new AuthorId('u'))->bySections('news')->byStates(ItemState::Idea)
            ->byAuthors(new AuthorId('v'));

        self::assertTrue($filter->accepts($this->item('v', 'news')));
        self::assertFalse($filter->accepts($this->item('u', 'news')));
        self::assertFalse($filter->accepts($this->item('v', 'blog')));

        $sections = Filter::all()->byStates(ItemState::Drafting)->byAuthors(new AuthorId('u'))->bySections('blog');
        self::assertFalse($sections->accepts($this->item('u', 'blog')));
        self::assertFalse($sections->accepts($this->item('v', 'blog')->transitionTo(ItemState::Drafting)));
        self::assertTrue($sections->accepts($this->item('u', 'blog')->transitionTo(ItemState::Drafting)));

        $states = Filter::all()->bySections('blog')->byAuthors(new AuthorId('u'))->byStates(ItemState::Idea);
        self::assertFalse($states->accepts($this->item('u', 'news')));
        self::assertFalse($states->accepts($this->item('v', 'blog')));
        self::assertTrue($states->accepts($this->item('u', 'blog')));
    }

    private function item(string $author, ?string $section): PlannedItem
    {
        return PlannedItem::plan('a', ResourceReference::of('article', '1'), 'T', 'fr', new AuthorId($author), null, $section);
    }
}
