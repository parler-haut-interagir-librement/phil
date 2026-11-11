<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Phil\Markup\Lexer\Cursor;
use Phil\Markup\Lexer\Slugger;
use Phil\Markup\Reader\BBCode\Node;
use Phil\Markup\Reader\BBCode\Options;
use Phil\Markup\Reader\Markdown\Inline;
use Phil\Markup\Reader\MediaWiki\MediaWikiSyntax;
use Phil\Markup\Reader\Wiki\Dialect;
use Phil\Markup\Reader\Wiki\Structures;
use Phil\Markup\Reader\Wiki\Tag;
use Phil\Markup\Reader\Wiki\Toggle;
use Phil\Markup\Reader\Wiki\WikiLinks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tag::class)]
#[CoversClass(Dialect::class)]
#[CoversClass(Toggle::class)]
#[CoversClass(WikiLinks::class)]
#[CoversClass(Structures::class)]
#[CoversClass(MediaWikiSyntax::class)]
#[CoversClass(Inline::class)]
#[CoversClass(Options::class)]
#[CoversClass(Node::class)]
#[CoversClass(Cursor::class)]
#[CoversClass(Slugger::class)]
final class WikiPartsTest extends TestCase
{
    public function testATagIsReadFromWhatStandsBetweenItsDelimiters(): void
    {
        $tag = Tag::parse('Note title="A b" wide K=v');

        self::assertInstanceOf(Tag::class, $tag);
        self::assertSame(['note', ['title' => 'A b', 'k' => 'v'], ['wide'], false, false], [$tag->name, $tag->attributes, $tag->flags, $tag->closing, $tag->void]);
        self::assertSame(['note', 'A b', '', 'wide'], [$tag->key(), $tag->attribute('title'), $tag->attribute('none'), $tag->first()]);

        $closing = Tag::parse('/note');
        self::assertInstanceOf(Tag::class, $closing);
        self::assertSame(['/note', true, false], [$closing->key(), $closing->closing, $closing->void]);

        $void = Tag::parse('ref name=a/');
        self::assertInstanceOf(Tag::class, $void);
        self::assertSame(['ref', ['name' => 'a'], true, null], [$void->name, $void->attributes, $void->void, $void->first()]);

        self::assertNull(Tag::parse(''));
        self::assertNull(Tag::parse(' note'));
        self::assertNull(Tag::parse('note-x'));
        self::assertNull(Tag::parse('/note x'));
        self::assertNull(Tag::parse('/'));
        self::assertInstanceOf(Tag::class, Tag::parse('/note '));
        self::assertInstanceOf(Tag::class, Tag::parse('br /'));
    }

    public function testATagBecomesTheNodeOfTheBBCodeReader(): void
    {
        $node = new Tag('spacer', ['k' => 'v'], ['3', 'x'])->node('3');

        self::assertSame(['spacer', '3', ['k' => 'v'], ['3', 'x']], [$node->name, $node->option, $node->attributes, $node->flags]);
        self::assertSame([false, false, [], []], [new Tag('x')->closing, new Tag('x')->void, new Tag('x')->attributes, new Tag('x')->flags]);
    }

    public function testEachWikiHasItsDelimiters(): void
    {
        self::assertSame(['<', '>', '</note>'], [Dialect::MediaWiki->opener(), Dialect::MediaWiki->closer(), Dialect::MediaWiki->closing('note')]);
        self::assertSame(['<', '>'], [Dialect::DokuWiki->opener(), Dialect::DokuWiki->closer()]);
        self::assertSame(['<<', '>>', '<</note>>'], [Dialect::Creole->opener(), Dialect::Creole->closer(), Dialect::Creole->closing('note')]);
    }

    public function testAMarkIsTextUntilItIsPaired(): void
    {
        $toggle = new Toggle("''", 'i');

        self::assertSame('&apos;&apos;', $toggle->html());
        $toggle->opens = true;
        self::assertSame('<i>', $toggle->html());
        $toggle->opens = false;
        self::assertSame('</i>', $toggle->html());
    }

    public function testPagesAndMediaHaveTheirPaths(): void
    {
        $links = new WikiLinks();

        self::assertSame(['/', '/'], [$links->pages, $links->media]);
        self::assertSame('/Main_Page', $links->page(' Main Page '));
        self::assertSame('/Help:Contents/a_b%3F', $links->page('Help:Contents/a b?'));
        self::assertSame('/Page#some-section', $links->page('Page#Some Section'));
        self::assertSame('#top', $links->page('#Top'));
        self::assertSame('#top', $links->page(' #Top'));
        self::assertSame('https://example.com/a', $links->page(' https://example.com/a '));
        self::assertSame('https://www.example.com', $links->page('www.example.com'));
        self::assertSame('https://WWW.example.com', $links->media('WWW.example.com'));
        self::assertSame('mailto:a@b.ca', $links->page('mailto:a@b.ca'));
        self::assertSame('/a_b.png', $links->media('a b.png'));
        self::assertSame('http://e.x/a.png', $links->media('http://e.x/a.png'));
        self::assertSame('/wiki/P', new WikiLinks('/wiki/', 'x')->page('P'));
        self::assertSame('xm.png', new WikiLinks('/wiki/', 'x')->media('m.png'));
        self::assertTrue(WikiLinks::isAddress(' HTTPS://a'));
        self::assertFalse(WikiLinks::isAddress('ftp://a'));
        self::assertTrue(WikiLinks::isAddressAt('[http://a', 1));
        self::assertTrue(WikiLinks::isAddressAt('xMailTo:a', 1));
        self::assertTrue(WikiLinks::isAddressAt('www.', 0));
        self::assertFalse(WikiLinks::isAddressAt('[http://a', 0));
        self::assertFalse(WikiLinks::isAddressAt('http:/', 0));
    }

    public function testTheCellsOfARowAreSplitOutOfLinksAndImages(): void
    {
        self::assertSame([['|', ' a '], ['^', ' [[b|c]] '], ['|', ' {{d|e}} ']], Structures::cells('| a ^ [[b|c]] | {{d|e}} |  ', '^|'));
        self::assertSame([['|', ' [x '], ['|', ' {y '], ['|', ' [[z '], ['|', ' w']], Structures::cells('| [x | {y | [[z | w', '|'));
        self::assertSame([['|', ' {{a '], ['|', ' [[b '], ['|', '{']], Structures::cells('| {{a | [[b |{', '|'));
        self::assertSame([], Structures::cells('|', '|'));
        self::assertSame([['|', '']], Structures::cells('||', '|'));
    }

    public function testTheParametersOfATemplateAreSplitOutOfWhatItHolds(): void
    {
        self::assertSame(['a', ' [[b|c]] ', '{{d|e}}', ''], MediaWikiSyntax::parameters('a| [[b|c]] |{{d|e}}|'));
        self::assertSame(['a]]', 'b'], MediaWikiSyntax::parameters('a]]|b'));
        self::assertSame(['[a', 'b}'], MediaWikiSyntax::parameters('[a|b}'));
        self::assertSame(['a', '{{b|{{c}}|d}}', 'e'], MediaWikiSyntax::parameters('a|{{b|{{c}}|d}}|e'));
        self::assertSame(['a', '[[b|[[c]]|d]]', 'e'], MediaWikiSyntax::parameters('a|[[b|[[c]]|d]]|e'));

        $tag = MediaWikiSyntax::tag('Page break | frontCover | Color = red | 1=x | a b=c');
        self::assertSame(['pagebreak', ['color' => 'red'], ['frontCover', '1=x', 'a b=c']], [$tag->name, $tag->attributes, $tag->flags]);
        self::assertSame(['k' => 'v=w'], MediaWikiSyntax::tag('x|k=v=w')->attributes);
        self::assertSame(['a:b', []], [MediaWikiSyntax::tag('A:b|c')->name, MediaWikiSyntax::tag('A:b|c')->attributes]);
        $function = MediaWikiSyntax::tag('#var:hero|x');
        self::assertSame(['var', [], ['hero', 'x']], [$function->name, $function->attributes, $function->flags]);
    }

    public function testInlineContentIsJoinedInOrder(): void
    {
        $joined = Inline::join([Inline::html('a'), Inline::markdown('b'), Inline::join([], '|'), Inline::html('c')], '|');

        self::assertSame([[true, 'a'], [true, '|'], [false, 'b'], [true, '|'], [true, '|'], [true, 'c']], $joined->pieces);
        self::assertSame([], Inline::join([], '|')->pieces);
    }
}
