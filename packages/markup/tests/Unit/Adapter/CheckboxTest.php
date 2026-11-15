<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Adapter;

use Phil\Markup\Adapter\AmmoniaPurifier;
use Phil\Markup\Adapter\CheckboxText;
use Phil\Markup\Adapter\CodeClasses;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * The checkboxes of checklists: kept as disabled checkboxes at `all`, written as text below it, and no other input
 * kept at any level.
 */
#[CoversClass(CheckboxText::class)]
#[CoversClass(SanitizerPurifier::class)]
#[CoversClass(AmmoniaPurifier::class)]
#[CoversClass(CodeClasses::class)]
#[CoversClass(DefaultCompiler::class)]
#[CoversClass(Markup::class)]
final class CheckboxTest extends TestCase
{
    private const string CHECKLIST = "- [x] done *now*\n- [ ] todo\n  - [X] sub";

    /**
     * @return iterable<string, array{Level, string}>
     */
    public static function levels(): iterable
    {
        yield 'basic' => [Level::Basic, '☑ done <em>now</em>☐ todo☑ sub'];
        yield 'forum' => [Level::Forum, '<ul><li>☑ done <em>now</em></li><li>☐ todo<ul><li>☑ sub</li></ul></li></ul>'];
        yield 'all' => [Level::All, '<ul><li><label><input type="checkbox" disabled checked />done <em>now</em></label></li><li><label><input type="checkbox" disabled />todo</label><ul><li><label><input type="checkbox" disabled checked />sub</label></li></ul></li></ul>'];
    }

    #[DataProvider('levels')]
    public function testEachLevelSaysTheStateOfAChecklist(Level $level, string $html): void
    {
        self::assertSame($html, new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies())->toHtml(self::CHECKLIST, MarkupFormat::Markdown, $level));
    }

    public function testTheCompilerWritesTheStateAsTextBelowAll(): void
    {
        $compiler = new DefaultCompiler();

        self::assertSame('<ul class="ujo-list ujo-list--checklist"><li data-checked="true"><label>☑ a</label></li><li data-checked="false"><label>☐ b</label></li></ul>', $compiler->compile("- [x] a\n- [ ] b", MarkupFormat::Markdown));
        self::assertSame('<ul class="ujo-list ujo-list--checklist"><li data-checked="true"><label>☑ a</label></li></ul>', $compiler->compileFor('[list=check][*=x]a[/list]', MarkupFormat::BBCode, Level::Basic));
        self::assertSame('<ul class="ujo-list ujo-list--checklist"><li data-checked="true"><label><input type="checkbox" disabled checked>a</label></li></ul>', $compiler->compileFor('* [x] a', MarkupFormat::MediaWiki, Level::All));
    }

    public function testTheFilterLeavesHtmlWithoutCheckboxesAsItIs(): void
    {
        $filter = new CheckboxText();

        self::assertSame('<p>a</p>', $filter->filter('<p>a</p>'));
        self::assertSame('<input type="text">', $filter->filter('<input type="text">'));
        self::assertSame("\u{2611} a\u{2610} b", $filter->filter('<input type="checkbox" disabled checked>a<input type="checkbox" disabled>b'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function inputs(): iterable
    {
        yield 'a checkbox, written again' => ['<input type="CheckBox" checked="no" name="n" value="v" form="f" onclick="x" id="i">a', '<input type="checkbox" disabled checked />a'];
        yield 'an unchecked checkbox, disabled' => ['<input type="checkbox">a', '<input type="checkbox" disabled />a'];
        yield 'the first type wins' => ['<input type="checkbox" type="text">', '<input type="checkbox" disabled />'];
        yield 'a text field' => ['<input type="text" value="password" disabled>a', 'a'];
        yield 'an input without type' => ['<p>a<input>b</p>', '<p>ab</p>'];
        yield 'a hidden input' => ['<input type="hidden" name="csrf" value="x">', ''];
        yield 'an image input' => ['<input type="image" src="https://example.com/a.png">', ''];
        yield 'a type in a value' => ['<input title="type=&quot;checkbox&quot;">', ''];
        yield 'two inputs' => ['<input type="checkbox" checked><input type="radio" checked><input type="checkbox">', '<input type="checkbox" disabled checked /><input type="checkbox" disabled />'];
        yield 'text that looks like an input' => ['&lt;input type="text"&gt;', '&lt;input type&#61;&#34;text&#34;&gt;'];
    }

    #[DataProvider('inputs')]
    public function testOnlyTheCheckboxesOfChecklistsAreKeptAtAll(string $html, string $purified): void
    {
        $purifier = SanitizerPurifier::defaultPolicies();

        self::assertSame($purified, $purifier->purify($html, Level::All));
        self::assertSame($purified, $purifier->purify($purified, Level::All));
    }

    public function testNoInputIsKeptBelowAll(): void
    {
        $purifier = SanitizerPurifier::defaultPolicies();
        $html = '<ul><li><label><input type="checkbox" disabled checked>a</label></li></ul>';

        self::assertSame('<ul><li>a</li></ul>', $purifier->purify($html, Level::Forum));
        self::assertSame('a', $purifier->purify($html, Level::Basic));
    }

    #[RequiresPhpExtension('ammonia')]
    public function testTheExtensionGivesInputsToTheSanitizer(): void
    {
        $html = str_repeat('<p>x</p>', 100).'<ul><li><label><INPUT type="checkbox" checked>a</label></li></ul><input type="text">';

        self::assertSame(SanitizerPurifier::defaultPolicies()->purify($html, Level::All), AmmoniaPurifier::defaultPolicies()->purify($html, Level::All));
    }
}
