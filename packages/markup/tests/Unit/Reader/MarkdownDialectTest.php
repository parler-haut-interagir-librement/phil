<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Override;
use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Reader\Markdown\BaseDialect;
use Phil\Markup\Reader\Markdown\BlockParser;
use Phil\Markup\Reader\Markdown\Context;
use Phil\Markup\Reader\Markdown\Contract\BlockRule;
use Phil\Markup\Reader\Markdown\FrontMatter;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\Markdown\InlineParser;
use Phil\Markup\Reader\Markdown\MarkdownReader;
use Phil\Markup\Reader\Markdown\MarkdownSyntax;
use Phil\Markup\Reader\Markdown\Rule\DelimiterRule;
use Phil\Markup\Reader\Markdown\Rule\InlineRule;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BaseDialect::class)]
#[CoversClass(MarkdownReader::class)]
#[CoversClass(BlockParser::class)]
#[CoversClass(InlineParser::class)]
#[CoversClass(Context::class)]
#[CoversClass(FrontMatter::class)]
#[CoversClass(DelimiterRule::class)]
#[CoversClass(MarkdownSyntax::class)]
final class MarkdownDialectTest extends TestCase
{
    private const string SOURCE = "%% title: T\n\n[name]: Ann\n[x]: /x\n\n> Hi @name, see [x] and ^up^.\n\n::: note\nIn\n:::\n\n!! loud\n\nTerm\n;;   one\n;; two\n\n?? a b\n\n=>   right";

    /**
     * A dialect that changes nothing reads as plain Markdown.
     */
    public function testADialectThatChangesNothingReadsAsMarkdown(): void
    {
        $plain = new MarkdownReader()->read(self::SOURCE);
        $dialect = new MarkdownReader(false, null, new class extends BaseDialect {
        })->read(self::SOURCE);

        self::assertEquals($plain, $dialect);
        self::assertSame('note', $plain->document->blocks[2]->type);
        self::assertStringContainsString('<a href="/x">x</a>', $this->html($plain->document));
    }

    /**
     * Each point of a dialect: its metadata, its definitions kept for its inline rule, its delimiters, its block
     * rule, the syntax it turns off, the mark of its definitions and the mark of its paragraphs.
     */
    public function testEachPointOfADialectReadsItsSyntax(): void
    {
        $reading = new MarkdownReader(false, null, $this->dialect())->read(self::SOURCE);

        self::assertSame(['title' => 'T'], $reading->metadata);
        self::assertSame(
            "<figure class=\"ujo-quote\"><blockquote>Hi <b>Ann</b>, see <a href=\"/x\">x</a> and <sup>up</sup>.</blockquote></figure>\n"
            ."<p class=\"ujo-paragraph\">::: note\nIn\n:::</p>\n"
            ."<div class=\"ujo-spacer\" aria-hidden=\"true\" data-lines=\"2\"></div>\n"
            ."<p class=\"ujo-paragraph\">loud</p>\n"
            ."<dl class=\"ujo-definition-list\"><dt>Term</dt><dd>one</dd><dd>two</dd><dt>a</dt><dd>1</dd><dt>b</dt><dd>2</dd></dl>\n"
            .'<p class="ujo-paragraph ujo-align-right">right</p>',
            $this->html($reading->document),
        );
    }

    /**
     * A line the block rule of the dialect starts ends the paragraph above it, as a built-in block does.
     */
    public function testABlockRuleOfTheDialectEndsAParagraph(): void
    {
        $blocks = new MarkdownReader(false, null, $this->dialect())->read("text\n!! loud")->document->blocks;

        self::assertSame(['paragraph', 'spacer', 'paragraph'], array_map(static fn (Block $block): string => $block->type, $blocks));
    }

    /**
     * The dialect keeps nothing between two readings: what one reading defines, the next does not know.
     */
    public function testWhatOneReadingDefinesStaysInIt(): void
    {
        $reader = new MarkdownReader(false, null, $this->dialect());
        $reader->read("[name]: Ann\n\nx");

        self::assertSame('<p class="ujo-paragraph">@name</p>', $this->html($reader->read('@name')->document));
    }

    private function dialect(): BaseDialect
    {
        return new class extends BaseDialect {
            #[Override]
            public function inlineRules(): array
            {
                return [new DelimiterRule('^'), new class implements InlineRule {
                    public function triggers(): string
                    {
                        return '@';
                    }

                    public function lex(Cursor $cursor, InlineParser $parser): ?string
                    {
                        $start = $cursor->position();
                        $cursor->advance();
                        $name = $cursor->consumeWhile('abcdefghijklmnopqrstuvwxyz');
                        $value = $parser->context->extra['name:'.$name] ?? null;

                        if (!\is_string($value)) {
                            $cursor->moveTo($start);

                            return null;
                        }

                        return '<b>'.$parser->parse($value).'</b>';
                    }
                }];
            }

            #[Override]
            public function blockRules(): array
            {
                return [new class implements BlockRule {
                    public function read(array $lines, int $at, Context $context): ?array
                    {
                        if (str_starts_with($lines[$at], '?? ')) {
                            $items = array_map(static fn (string $term): array => ['term' => Inline::markdown($term), 'definitions' => [Inline::markdown((string) (\ord($term[0]) - 96))]], explode(' ', substr($lines[$at], 3)));

                            return [[$context->block('definitionList', ['items' => $items])], $at + 1];
                        }

                        if (!str_starts_with($lines[$at], '!! ')) {
                            return null;
                        }

                        return [[$context->block('spacer', ['lines' => 2]), $context->block('paragraph', ['text' => Inline::markdown(substr($lines[$at], 3))])], $at + 1];
                    }
                }];
            }

            #[Override]
            public function disables(): array
            {
                return [MarkdownSyntax::ColonBlocks];
            }

            #[Override]
            public function frontMatter(array $lines): ?array
            {
                return str_starts_with($lines[0] ?? '', '%% ') ? [FrontMatter::yaml(substr($lines[0], 3)) ?? [], \array_slice($lines, 1)] : null;
            }

            #[Override]
            public function define(string $label, string $value, Context $context): bool
            {
                if (str_starts_with($value, '/')) {
                    return false;
                }

                $context->extra['name:'.$label] = $value;

                return true;
            }

            #[Override]
            public function definition(string $line): ?string
            {
                return str_starts_with($line, ';;') ? ltrim(substr($line, 2)) : null;
            }

            #[Override]
            public function paragraph(string $text): array
            {
                return str_starts_with($text, '=> ') ? [substr($text, 3), ['alignment' => ['alignment' => 'right']]] : [$text, []];
            }
        };
    }

    private function html(Document $document): string
    {
        return HtmlRenderer::default()->render($document);
    }
}
