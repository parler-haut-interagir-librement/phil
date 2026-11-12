<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Preview;

use Phil\SocialCards\Preview\PageTags;
use Phil\SocialCards\Preview\TagReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TagReader::class)]
#[CoversClass(PageTags::class)]
final class TagReaderTest extends TestCase
{
    public function testTheTagsOfAPage(): void
    {
        $html = <<<'HTML'
            <!doctype html>
            <html lang="fr"><head>
            <meta charset="utf-8">
            <title>  Le   titre
            de la page </title>
            <link rel="stylesheet" href="/a.css">
            <link rel="alternate canonical" href="/fr/page">
            <link rel="canonical" href="/second">
            <meta property="og:title" content="Le titre">
            <meta property="OG:Image" content="/images/a.png">
            <meta property="og:image" content="https://cdn.example.com/b.png">
            <meta name="description" content="  Une
            description ">
            <meta name="twitter:card" content="summary_large_image">
            <meta property="og:empty" content="  ">
            <meta property="" content="no key">
            <meta content="no key at all">
            <meta property="og:nothing">
            <meta name="ignored" property=" og:type " content="article">
            <link rel="CANONICAL" href="/upper">
            </head><body><meta property="og:body" content="after the head"></body></html>
            HTML;

        $tags = new TagReader()->read($html, 'https://example.com/fr/page?x=1');

        self::assertSame('https://example.com/fr/page?x=1', $tags->url);
        self::assertSame('Le titre de la page', $tags->title);
        self::assertSame('https://example.com/fr/page', $tags->canonical);
        self::assertSame([
            'og:title' => ['Le titre'],
            'og:image' => ['/images/a.png', 'https://cdn.example.com/b.png'],
            'description' => ['Une description'],
            'twitter:card' => ['summary_large_image'],
            'og:type' => ['article'],
        ], $tags->meta);
        self::assertSame('/images/a.png', $tags->get('OG:IMAGE'));
        self::assertSame(['/images/a.png', 'https://cdn.example.com/b.png'], $tags->all('og:Image'));
        self::assertSame([], $tags->all('og:video'));
        self::assertNull($tags->get('og:video'));
        self::assertSame('Le titre', $tags->first('og:video', 'og:title', 'description'));
        self::assertNull($tags->first('og:video'));
    }

    public function testTheBoundsOfTheReader(): void
    {
        $many = '<head>'.str_repeat('<meta property="og:image" content="x">', 20).str_repeat('<meta name="a" content="b">', 300).'<meta name="late" content="c"><link rel="canonical" href="/c">';
        $tags = new TagReader()->read($many, 'https://example.com/');

        self::assertCount(10, $tags->all('og:image'));
        self::assertCount(10, $tags->all('a'));
        self::assertNull($tags->get('late'), 'Only the first 300 tags are read.');
        self::assertSame('https://example.com/c', $tags->canonical);

        $links = '<head>'.str_repeat('<link rel="icon" href="/i">', 300).'<link rel="canonical" href="/c">';
        self::assertNull(new TagReader()->read($links, 'https://example.com/')->canonical, 'Only the first 300 links are read.');
        $links = '<head>'.str_repeat('<link rel="icon" href="/i">', 299).'<link rel="canonical" href="/c">';
        self::assertSame('https://example.com/c', new TagReader()->read($links, 'https://example.com/')->canonical);
        $tags = new TagReader()->read('<head>'.str_repeat('<meta name="a" content="b">', 299).'<meta name="last" content="c">', 'https://example.com/');
        self::assertSame('c', $tags->get('last'));

        $long = new TagReader()->read('<meta name="'.str_repeat('k', 101).'" content="v"><meta name="'.str_repeat('k', 100).'" content="'.str_repeat('v', 3000).'">', 'https://example.com/');
        self::assertSame([str_repeat('k', 100)], array_keys($long->meta));
        self::assertSame(2048, mb_strlen((string) $long->get(str_repeat('k', 100))));

        $tag = '<meta name="a" content="b">';
        $page = str_repeat(' ', TagReader::MAX_BYTES - \strlen($tag)).$tag;
        self::assertSame('b', new TagReader()->read($page, 'https://example.com/')->get('a'), 'The last byte read closes the tag.');
        self::assertNull(new TagReader()->read(' '.$page, 'https://example.com/')->get('a'), 'One byte further, the tag is cut, and a cut tag is dropped.');
        self::assertSame(524288, TagReader::MAX_BYTES);

        $cut = new TagReader(40)->read('<head><meta name="a" content="b"><meta name="c" content="d"></head>', 'https://example.com/');
        self::assertSame(['a' => ['b']], $cut->meta);
    }

    public function testWhatIsNotAPage(): void
    {
        foreach (['', "\0\xFF\xFE", '{"json": true}', '<<<<>>>>', str_repeat('<div>', 1000)] as $html) {
            $tags = new TagReader()->read($html, 'https://example.com/');
            self::assertSame([null, null, []], [$tags->title, $tags->canonical, $tags->meta]);
        }

        self::assertNull(new TagReader()->read('<title> </title><link rel="canonical" href="javascript:x">', 'https://example.com/')->title);
        self::assertNull(new TagReader()->read('<link rel="canonical" href="javascript:x">', 'https://example.com/')->canonical);
        self::assertNull(new TagReader()->read('<link rel="canonicalish" href="/x">', 'https://example.com/')->canonical);
        self::assertSame('https://example.com/x', new TagReader()->read('<link rel="Canonical" href="/x">', 'https://example.com/')->canonical);
        self::assertNull(new TagReader()->read('<link href="/x">', 'https://example.com/')->canonical);
    }

    public function testTheCharacterSet(): void
    {
        $latin1 = "<head><meta property=\"og:title\" content=\"\xE9t\xE9\"></head>";

        self::assertSame('été', new TagReader()->read($latin1, 'https://example.com/', 'ISO-8859-1')->get('og:title'));
        self::assertSame('été', new TagReader()->read('<meta charset="iso-8859-1">'.$latin1, 'https://example.com/')->get('og:title'));
        self::assertSame('été', new TagReader()->read('<meta property="og:title" content="été">', 'https://example.com/', 'no-such-charset')->get('og:title'));
    }
}
