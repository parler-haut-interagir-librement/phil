<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Benchmark;

use Generator;
use Phil\MentionsBundle\Application\Extraction\TextMentions;
use Phil\MentionsBundle\Application\Extraction\UjoMentions;
use Phil\MentionsBundle\Application\Rendering\MentionLinker;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use Phil\MentionsBundle\Tests\Unit\Fixture\Directory;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * Every saved comment is read for mentions, and every comment shown is linked: a short comment must cost a few
 * microseconds, and a text of hostile shape at the length limit — a hundred thousand characters of `@`, of backticks,
 * of unclosed fences — a time bounded by its length.
 *
 * @BeforeMethods("setUp")
 */
final class MentionsBench
{
    private readonly TextMentions $text;

    private readonly MentionLinker $linker;

    private string $input = '';

    private Document $document;

    public function __construct()
    {
        $this->text = new TextMentions();
        $this->linker = new MentionLinker(new Directory(), MentionPattern::default(), 20);
        $this->document = new Document();
    }

    /**
     * @param array{shape: string} $params
     */
    public function setUp(array $params): void
    {
        $this->input = match ($params['shape']) {
            'comment' => "Thanks @ada, this reads well. Could @bob check the `@code` part?\n\nSee [the rules](https://example.test/@rules).",
            'ats' => str_repeat('@', 100_000),
            'handles' => str_repeat('@ada ', 20_000),
            'backticks' => str_repeat('`` @a ', 16_000),
            default => str_repeat("```\n@a\n", 14_000),
        };
        $this->document = new Document(array_map(
            static fn (int $i): Block => new Block('b'.$i, 'paragraph', ['text' => 'Hi <a href="/m/ada" data-mention="user:1">@Ada</a>, see <b>this</b>.']),
            range(1, 50),
        ));
    }

    /**
     * @ParamProviders("texts")
     *
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchMarkdown(): void
    {
        $this->text->find($this->input, true);
    }

    /**
     * @ParamProviders("texts")
     *
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchLink(): void
    {
        $this->linker->link(htmlspecialchars($this->input, \ENT_QUOTES));
    }

    /**
     * @ParamProviders("comment")
     *
     * @Revs(100)
     *
     * @Iterations(3)
     */
    public function benchDocument(): void
    {
        UjoMentions::find($this->document);
    }

    /**
     * @return Generator<string, array{shape: string}>
     */
    public function texts(): Generator
    {
        foreach (['comment', 'ats', 'handles', 'backticks', 'fences'] as $shape) {
            yield $shape => ['shape' => $shape];
        }
    }

    /**
     * @return Generator<string, array{shape: string}>
     */
    public function comment(): Generator
    {
        yield 'comment' => ['shape' => 'comment'];
    }
}
