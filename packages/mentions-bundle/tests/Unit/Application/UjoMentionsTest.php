<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Application;

use Phil\MentionsBundle\Application\Extraction\UjoMentions;
use Phil\MentionsBundle\Domain\ValueObject\Mention;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UjoMentions::class)]
final class UjoMentionsTest extends TestCase
{
    public function testTheMarksOfADocumentInReadingOrder(): void
    {
        $document = new Document([
            new Block('a', 'paragraph', ['text' => 'Hi <a href="/members/ada" data-mention="user:1">@Ada</a> and <a data-mention="user:2">@<b>Bob</b> &amp; co</a>.']),
            new Block('b', 'list', ['items' => [['content' => '<a href="/x">plain link</a>', 'items' => []], ['content' => '<a  class="m" data-mention="user:1" href="#">  @Ada  </a>']]]),
            new Block('c', 'paragraph', ['text' => 'No mark, @chloé.', 'level' => 2]),
            new Block('d', 'paragraph', ['text' => '<a data-mention="nope">@x</a><a data-mention="team:a&amp;b">@Team</a><a data-mention="user:o&apos;k">@O&apos;Hara &quot;Kit&quot;</a>']),
        ]);

        self::assertSame(
            [['user:1', '@Ada'], ['user:2', '@Bob & co'], ['user:1', '@Ada'], ['team:a&b', '@Team'], ["user:o'k", '@O\'Hara "Kit"']],
            array_map(static fn (Mention $mention): array => [(string) $mention->member?->toString(), $mention->label], UjoMentions::find($document)),
        );
    }

    public function testADocumentWithoutMarks(): void
    {
        self::assertSame([], UjoMentions::find(new Document()));
        self::assertSame([], UjoMentions::find(new Document([new Block('a', 'paragraph', ['text' => 'data-mention but no mark'])])));
        self::assertSame([], UjoMentions::find(new Document([new Block('a', 'paragraph', ['text' => '<a data-mention="user:1">unclosed'])])));
    }
}
