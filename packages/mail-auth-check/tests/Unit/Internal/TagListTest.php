<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Internal;

use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Internal\TagList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TagList::class)]
#[CoversClass(InvalidRecord::class)]
final class TagListTest extends TestCase
{
    public function testTagsAreReadInOrderWithTheirSpacesTrimmed(): void
    {
        self::assertSame(
            ['v' => 'DMARC1', 'p' => 'reject', 'rua' => 'mailto:a@example.org', 'x_1' => ''],
            TagList::parse(" v = DMARC1;\tp=reject ;;rua=mailto:a@example.org; x_1=;\r\n", 'DMARC'),
        );
    }

    public function testAValueMayHoldAnEqualsSign(): void
    {
        self::assertSame(['p' => 'abc=='], TagList::parse('p=abc==', 'DKIM'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidLists(): iterable
    {
        yield 'empty' => ['', 'The DKIM record is empty.'];
        yield 'separators alone' => [' ; ;', 'The DKIM record is empty.'];
        yield 'no equals sign' => ['v=DKIM1; p', 'The DKIM record has a part that is not "tag=value".'];
        yield 'an empty name' => ['=x', 'The DKIM record has a part that is not "tag=value".'];
        yield 'a name starting with a digit' => ['1a=x', 'The DKIM record has a part that is not "tag=value".'];
        yield 'a name with a space' => ['a b=x', 'The DKIM record has a part that is not "tag=value".'];
        yield 'a tag twice' => ['p=a; p=b', 'The DKIM record gives the tag "p" twice.'];
        yield 'too long' => [str_repeat('a', 4097), 'The DKIM record is longer than 4096 bytes.'];
    }

    #[DataProvider('invalidLists')]
    public function testAnInvalidListIsRefused(string $text, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        TagList::parse($text, 'DKIM');
    }

    public function testANameMayHaveTrailingSpaces(): void
    {
        self::assertSame(['a' => 'x'], TagList::parse("a \t=x", 'DKIM'));
    }

    public function testA4096ByteListIsRead(): void
    {
        self::assertSame(['a' => str_repeat('b', 4094)], TagList::parse('a='.str_repeat('b', 4094), 'DKIM'));
    }

    public function testThirtyTwoTagsAreReadAndThirtyThreeRefused(): void
    {
        $tags = array_map(static fn (int $i): string => 't'.$i.'=x', range(1, 32));
        self::assertCount(32, TagList::parse(implode(';', $tags), 'DMARC'));

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('The DMARC record has more than 32 tags.');

        TagList::parse(implode(';', $tags).';t33=x', 'DMARC');
    }
}
