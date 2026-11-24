<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit;

use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkResult::class)]
#[CoversClass(ContentLinks::class)]
final class LinkResultTest extends TestCase
{
    public function testAResultGoesThroughAnArray(): void
    {
        $result = new LinkResult(LinkStatus::PermanentRedirect, 200, Url::fromString('https://example.com/new'), 'moved');
        $data = $result->toArray();

        self::assertSame(['status' => 'permanent-redirect', 'code' => 200, 'target' => 'https://example.com/new', 'reason' => 'moved'], $data);
        self::assertEquals($result, LinkResult::fromArray($data));

        $bare = new LinkResult(LinkStatus::Dead);
        self::assertSame(['status' => 'dead', 'code' => null, 'target' => null, 'reason' => null], $bare->toArray());
        self::assertEquals($bare, LinkResult::fromArray(['status' => 'dead']));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function otherShapes(): iterable
    {
        yield 'not an array' => ['alive'];
        yield 'no status' => [['code' => 200]];
        yield 'a status that is not a string' => [['status' => 1]];
        yield 'a string' => ['alive'];
        yield 'an unknown status' => [['status' => 'gone']];
        yield 'a code that is not an integer' => [['status' => 'alive', 'code' => '200']];
        yield 'a target that is not a string' => [['status' => 'alive', 'target' => 1]];
        yield 'a target that is not an address' => [['status' => 'alive', 'target' => 'nowhere']];
        yield 'a reason that is not a string' => [['status' => 'alive', 'reason' => 1]];
    }

    #[DataProvider('otherShapes')]
    public function testAnArrayOfAnotherShapeGivesNoResult(mixed $data): void
    {
        self::assertNull(LinkResult::fromArray($data));
    }

    public function testAContentKeepsWhatItIsGiven(): void
    {
        $page = Url::fromString('https://example.com/p');
        $content = new ContentLinks('42', $page, '<p>x</p>');

        self::assertSame('42', $content->id);
        self::assertSame($page, $content->page);
        self::assertSame('<p>x</p>', $content->html);
    }
}
