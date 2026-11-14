<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Application;

use Phil\MentionsBundle\Application\Extraction\TextMentions;
use Phil\MentionsBundle\Domain\ValueObject\Mention;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextMentions::class)]
final class TextMentionsTest extends TestCase
{
    public function testTheHandlesOfAPlainTextWithTheirCharacterOffsets(): void
    {
        $mentions = new TextMentions()->find("Thanks @ada — and «@Bob».\n@ada again, ada@example.com");

        self::assertSame([['ada', 7], ['Bob', 19], ['ada', 26]], $this->pairs($mentions));
        self::assertSame('@Bob', $mentions[1]->label);
    }

    public function testATextWithoutAtOrNotUtf8HoldsNoMention(): void
    {
        self::assertSame([], new TextMentions()->find('No mention here.'));
        self::assertSame([], new TextMentions()->find("@ada \xff"));
        self::assertSame([], new TextMentions()->find("@ada \xff", true));
        self::assertSame([], new TextMentions()->find('mail@example.com'));
    }

    public function testThePatternOfTheApplication(): void
    {
        $finder = new TextMentions(MentionPattern::of('[a-z]{2}'));

        self::assertSame([['ad', 0]], $this->pairs($finder->find('@ada @B')));
        self::assertSame('[a-z]{2}', $finder->pattern->handle);
        self::assertSame(MentionPattern::DEFAULT, new TextMentions()->pattern->handle);
    }

    /**
     * @return iterable<string, array{string, list<array{string, int}>}>
     */
    public static function markdown(): iterable
    {
        yield 'a fenced block' => ["@ada\n```php\n@bob\n```\n@chloé", [['ada', 0], ['chloé', 21]]];
        yield 'a fence of tildes, indented' => ["  ~~~\n@bob\n  ~~~\n@ada", [['ada', 17]]];
        yield 'a fence that never closes' => ["@ada\n```\n@bob", [['ada', 0]]];
        yield 'a longer fence closes only with as many' => ["````\n```\n@bob\n````\n@ada", [['ada', 19]]];
        yield 'a code span' => ['`@bob` and @ada', [['ada', 11]]];
        yield 'a code span of two backticks' => ['``a ` @bob`` @ada', [['ada', 13]]];
        yield 'a code span ends with its line' => ["`@bob\n@ada`", [['bob', 1], ['ada', 6]]];
        yield 'a link destination' => ['[@ada](https://example.test/@bob)', [['ada', 1]]];
        yield 'an image destination' => ['![x](/a/@bob.png) @ada', [['ada', 18]]];
        yield 'an autolink' => ['<mailto:x@bob> <https://x.test/?@bob> @ada', [['ada', 38]]];
        yield 'an escaped at' => ['\@bob @ada', [['ada', 6]]];
        yield 'offsets after multibyte code' => ['`éé` @ada', [['ada', 5]]];
    }

    /**
     * @param list<array{string, int}> $expected
     */
    #[DataProvider('markdown')]
    public function testMarkdownCodeAndAddressesAreNotRead(string $text, array $expected): void
    {
        self::assertSame($expected, $this->pairs(new TextMentions()->find($text, true)));
    }

    public function testPlainTextReadsWhatMarkdownWouldSkip(): void
    {
        self::assertSame([['bob', 1], ['ada', 7]], $this->pairs(new TextMentions()->find('`@bob` @ada')));
    }

    /**
     * @param list<Mention> $mentions
     *
     * @return list<array{?string, ?int}>
     */
    private function pairs(array $mentions): array
    {
        return array_map(static fn (Mention $mention): array => [$mention->handle, $mention->offset], $mentions);
    }
}
