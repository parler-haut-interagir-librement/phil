<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit;

use Closure;
use InvalidArgumentException;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InputTooComplex;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The budgets of the inline sanitizer: the tags and comments it reads in one fragment, and how deep the elements nest.
 * A fragment that spends a budget exactly is read; one tag or one level more is refused by an `InputTooComplex` that
 * names the budget, whatever the fragment's length in bytes.
 */
#[CoversClass(InputTooComplex::class)]
#[CoversClass(InlineSanitizer::class)]
final class InputBudgetTest extends TestCase
{
    public function testTheSanitizerReadsUpToItsBudgetOfTagsAndRefusesMore(): void
    {
        $sanitizer = new InlineSanitizer(maxTokens: 4);

        self::assertSame('<b>a</b><i>b</i>c', $sanitizer->sanitize('<b>a</b><i>b</i>c'));
        self::assertSame('abc', $sanitizer->toText('<b>a</b><i>b</i>c'));
        self::assertSame('ab', $sanitizer->sanitize('a<!-- 1 --><!-- 2 --><!-- 3 --><!-- 4 -->b'));
        $this->assertRefused('tokens', 4, static fn (): mixed => $sanitizer->sanitize('<b>a</b><i>b</i><u>'));
        $this->assertRefused('tokens', 4, static fn (): mixed => $sanitizer->toText('<b>a</b><i>b</i><br>'));
        $this->assertRefused('tokens', 4, static fn (): mixed => $sanitizer->sanitize('<!-- 1 --><!-- 2 --><!-- 3 --><!-- 4 --><!-- 5 -->'));
        $this->assertRefused('tokens', 4, static fn (): mixed => $sanitizer->sanitize('<script>x</script><script>y</script><b>'));
        $this->assertRefused('tokens', 4, static fn (): mixed => $sanitizer->withProfile('mine', InlineProfile::plain())->sanitize('<b></b><b></b><b>', 'mine'));
        self::assertSame(str_repeat('a', 100), $sanitizer->sanitize(str_repeat('a', 100)), 'Text spends no tag.');
        self::assertSame('a &lt; b', $sanitizer->sanitize('a < b'), 'A bare "<" is text.');
    }

    public function testTheSanitizerNestsUpToItsBudgetOfDepthAndRefusesMore(): void
    {
        $sanitizer = new InlineSanitizer(maxDepth: 3);

        self::assertSame('<b><i><u class="cdx-underline">a</u></i></b>', $sanitizer->sanitize('<b><i><u>a</u></i></b>'));
        self::assertSame('<b><i><u class="cdx-underline">a<br></u></i></b>', $sanitizer->sanitize('<b><i><u>a<br></u></i></b>'), 'A void element opens no level.');
        self::assertSame(str_repeat('<b>a</b>', 50), $sanitizer->sanitize(str_repeat('<b>a</b>', 50)), 'Elements closed give their level back.');
        self::assertSame('<b><i><u class="cdx-underline"></u></i></b>', $sanitizer->sanitize('<b><i><u></b><span>'), 'A stray end tag closes the levels it ends.');
        self::assertSame('abc', $sanitizer->toText('<b><i><u><s>a</s>b</u></i></b>c'), 'Text alone opens no level.');
        $this->assertRefused('depth', 3, static fn (): mixed => $sanitizer->sanitize('<b><i><u><s>a</s></u></i></b>'));
        $this->assertRefused('depth', 3, static fn (): mixed => $sanitizer->sanitize('<span><span><span><span>', 'plain'));
        $this->assertRefused('depth', 3, static fn (): mixed => $sanitizer->withProfile('mine', InlineProfile::plain())->sanitize('<b><b><b><b>', 'mine'));
    }

    /**
     * The defaults are larger than a field of the normalizer can spend: 100,000 characters hold fewer tags than the
     * budget, and a browser builds no deeper tree than 512 levels.
     */
    public function testTheDefaults(): void
    {
        self::assertSame(100_000, InlineSanitizer::DEFAULT_MAX_TOKENS);
        self::assertSame(512, InlineSanitizer::DEFAULT_MAX_DEPTH);

        $sanitizer = new InlineSanitizer();
        self::assertSame(str_repeat('<br>', 100_000), $sanitizer->sanitize(str_repeat('<br>', 100_000)));
        $this->assertRefused('tokens', 100_000, static fn (): mixed => $sanitizer->sanitize(str_repeat('<br>', 100_001)));
        self::assertSame(str_repeat('<b>', 512).str_repeat('</b>', 512), $sanitizer->sanitize(str_repeat('<b>', 512)));
        $this->assertRefused('depth', 512, static fn (): mixed => $sanitizer->sanitize(str_repeat('<b>', 513)));
    }

    /**
     * The normalizer reads every HTML field through its sanitizer, and its budgets.
     */
    public function testTheNormalizerHasTheBudgetsOfItsSanitizer(): void
    {
        $deep = new Document([new Block('a', 'paragraph', ['text' => '<b><i>a</i></b>'])]);
        $ujo = new Ujo(new Normalizer(sanitizer: new InlineSanitizer(maxDepth: 1)));

        $this->assertRefused('depth', 1, static fn (): mixed => $ujo->normalize($deep));
        $this->assertRefused('tokens', 3, static fn (): mixed => new Ujo(new Normalizer(sanitizer: new InlineSanitizer(maxTokens: 3)))->normalize($deep));
        self::assertSame('<b><i>a</i></b>', new Ujo(new Normalizer(sanitizer: new InlineSanitizer(maxTokens: 4, maxDepth: 2)))->normalize($deep)->blocks[0]->data['text']);
    }

    public function testTheExceptionNamesItsBudget(): void
    {
        $tokens = InputTooComplex::tokens(10);
        $depth = InputTooComplex::depth(7);

        self::assertSame('The input holds more than 10 tags and comments.', $tokens->getMessage());
        self::assertSame(['tokens', 10], [$tokens->budget, $tokens->limit]);
        self::assertSame('The input nests its elements deeper than 7 levels.', $depth->getMessage());
        self::assertSame(['depth', 7], [$depth->budget, $depth->limit]);
        self::assertInstanceOf(UjoException::class, $tokens);
        self::assertInstanceOf(InvalidArgumentException::class, $tokens);
    }

    /**
     * @param Closure(): mixed $call
     */
    private function assertRefused(string $budget, int $limit, Closure $call): void
    {
        try {
            $call();
            self::fail(\sprintf('The budget of %s at %d should be spent.', $budget, $limit));
        } catch (InputTooComplex $inputTooComplex) {
            self::assertSame([$budget, $limit], [$inputTooComplex->budget, $inputTooComplex->limit]);
        }
    }
}
