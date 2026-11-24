<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Unit\Domain;

use Phil\NewsletterArchive\Domain\Exception\InvalidArchiveValue;
use Phil\NewsletterArchive\Domain\ValueObject\ArchiveFilter;
use Phil\NewsletterArchive\Domain\ValueObject\IssuePage;
use Phil\NewsletterArchive\Domain\ValueObject\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A tag is a slug of at most 64 characters, tags are distinct and sorted, sixteen at most; a filter bounds its page and
 * its size; a page counts its pages and says whether others come before and after.
 */
#[CoversClass(Tag::class)]
#[CoversClass(ArchiveFilter::class)]
#[CoversClass(IssuePage::class)]
#[CoversClass(InvalidArchiveValue::class)]
final class ValueObjectsTest extends TestCase
{
    public function testATagIsASlug(): void
    {
        self::assertSame('game-night', Tag::of('game-night')->value);
        self::assertSame('game-night', (string) Tag::of('game-night'));
        self::assertSame(str_repeat('a', 64), Tag::of(str_repeat('a', 64))->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notTags(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Events'];
        yield 'space' => ['game night'];
        yield 'trailing hyphen' => ['events-'];
        yield 'too long' => [str_repeat('a', 65)];
    }

    #[DataProvider('notTags')]
    public function testWhatIsNotATagIsRefused(string $value): void
    {
        $this->expectException(InvalidArchiveValue::class);
        Tag::of($value);
    }

    public function testTheMessageOfARefusedTagSaysWhy(): void
    {
        try {
            Tag::of(str_repeat('a', 65));
            self::fail('A tag of 65 characters was accepted.');
        } catch (InvalidArchiveValue $invalidArchiveValue) {
            self::assertSame('A tag holds at most 64 characters.', $invalidArchiveValue->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('"Bad Tag" is not a tag: lowercase letters and digits, in words joined by single hyphens.');
        Tag::of('Bad Tag');
    }

    public function testTagsAreDistinctAndSorted(): void
    {
        self::assertSame(['events', 'news'], array_map(static fn (Tag $tag): string => $tag->value, Tag::list(['news', 'events', 'news'])));
        self::assertCount(16, Tag::list(array_map(static fn (int $i): string => 't'.$i, range(1, 16))));
    }

    public function testSeventeenTagsAreTooMany(): void
    {
        $this->expectExceptionMessageIsOrContains('An issue is filed under at most 16 tags.');
        Tag::list(array_map(static fn (int $i): string => 't'.$i, range(1, 17)));
    }

    public function testAFilterBoundsItsPageAndItsSize(): void
    {
        $filter = new ArchiveFilter('default', 'news', Tag::of('events'), 3, 10);
        self::assertSame(20, $filter->offset());
        self::assertSame(0, new ArchiveFilter('default')->offset());
        self::assertSame(20, new ArchiveFilter('default')->pageSize);
        self::assertSame(99_900, new ArchiveFilter('default', page: 1000, pageSize: 100)->offset());
        self::assertSame(0, new ArchiveFilter('default', page: 1, pageSize: 1)->offset());
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function badFilters(): iterable
    {
        yield 'page zero' => [0, 20, 'A page of the archive is a number from 1 to 1000.'];
        yield 'page too deep' => [1001, 20, 'A page of the archive is a number from 1 to 1000.'];
        yield 'size zero' => [1, 0, 'A page of the archive holds 1 to 100 issues.'];
        yield 'size too large' => [1, 101, 'A page of the archive holds 1 to 100 issues.'];
    }

    #[DataProvider('badFilters')]
    public function testAFilterOutOfBoundsIsRefused(int $page, int $size, string $message): void
    {
        $this->expectException(InvalidArchiveValue::class);
        $this->expectExceptionMessageIsOrContains($message);
        new ArchiveFilter('default', page: $page, pageSize: $size);
    }

    public function testAPageCountsItsPages(): void
    {
        self::assertSame(1, new IssuePage([], 0, new ArchiveFilter('default'))->pages());
        self::assertSame(1, new IssuePage([], 20, new ArchiveFilter('default'))->pages());
        self::assertSame(2, new IssuePage([], 21, new ArchiveFilter('default'))->pages());
        self::assertSame(1000, new IssuePage([], 1_000_000, new ArchiveFilter('default', pageSize: 1))->pages());

        $middle = new IssuePage([], 41, new ArchiveFilter('default', page: 2));
        self::assertTrue($middle->hasPrevious());
        self::assertTrue($middle->hasNext());

        $first = new IssuePage([], 41, new ArchiveFilter('default'));
        self::assertFalse($first->hasPrevious());
        self::assertTrue($first->hasNext());

        $last = new IssuePage([], 41, new ArchiveFilter('default', page: 3));
        self::assertTrue($last->hasPrevious());
        self::assertFalse($last->hasNext());
    }
}
