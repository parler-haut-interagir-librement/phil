<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit;

use DateTimeImmutable;
use Phil\SocialCards\MetaAttribute;
use Phil\SocialCards\MetaTag;
use Phil\SocialCards\MetaTags;
use Phil\SocialCards\ShareImage;
use Phil\SocialCards\ShareMetadata;
use Phil\SocialCards\ShareType;
use Phil\SocialCards\TwitterCard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetaTags::class)]
#[CoversClass(MetaTag::class)]
final class MetaTagsTest extends TestCase
{
    public function testTheTagsOfAnArticle(): void
    {
        $page = new ShareMetadata(
            'Le "jeu" & <moi>',
            'https://example.com/fr/jeu?a=1&b=2',
            'Une description',
            new ShareImage('https://example.com/jeu.png', 'Un dé', 1200, 630, 'image/png'),
            ShareType::Article,
            'fr_CA',
            ['en_CA', 'es'],
            'Le site',
            'Une autrice',
            new DateTimeImmutable('2026-11-01T08:00:00-05:00'),
            new DateTimeImmutable('2026-11-02T08:00:00+00:00'),
            'site',
            'creator',
            'me@example.social',
        );

        self::assertSame(<<<'HTML'
            <link rel="canonical" href="https://example.com/fr/jeu?a=1&amp;b=2">
            <meta name="description" content="Une description">
            <meta property="og:title" content="Le &quot;jeu&quot; &amp; &lt;moi&gt;">
            <meta property="og:type" content="article">
            <meta property="og:url" content="https://example.com/fr/jeu?a=1&amp;b=2">
            <meta property="og:description" content="Une description">
            <meta property="og:site_name" content="Le site">
            <meta property="og:locale" content="fr_CA">
            <meta property="og:locale:alternate" content="en_CA">
            <meta property="og:locale:alternate" content="es">
            <meta property="og:image" content="https://example.com/jeu.png">
            <meta property="og:image:type" content="image/png">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="Un dé">
            <meta property="article:published_time" content="2026-11-01T08:00:00-05:00">
            <meta property="article:modified_time" content="2026-11-02T08:00:00+00:00">
            <meta property="article:author" content="Une autrice">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="Le &quot;jeu&quot; &amp; &lt;moi&gt;">
            <meta name="twitter:description" content="Une description">
            <meta name="twitter:image" content="https://example.com/jeu.png">
            <meta name="twitter:image:alt" content="Un dé">
            <meta name="twitter:site" content="@site">
            <meta name="twitter:creator" content="@creator">
            <meta name="fediverse:creator" content="@me@example.social">
            HTML, MetaTags::html(MetaTags::of($page)));
    }

    public function testTheTagsOfAPlainPage(): void
    {
        $tags = MetaTags::of(new ShareMetadata('Title', 'https://example.com/', author: 'Not an article', publishedTime: new DateTimeImmutable('2026-11-01')));

        self::assertSame(<<<'HTML'
            <link rel="canonical" href="https://example.com/">
            <meta property="og:title" content="Title">
            <meta property="og:type" content="website">
            <meta property="og:url" content="https://example.com/">
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="Title">
            HTML, MetaTags::html($tags));
        self::assertEquals(new MetaTag(MetaAttribute::Link, 'canonical', 'https://example.com/'), $tags[0]);
        self::assertSame(range(0, 5), array_keys($tags), 'The tags left out leave no gap.');
    }

    public function testAnImageWithItsAddressOnlyAndAChosenCard(): void
    {
        $page = new ShareMetadata('Title', 'https://example.com/', image: new ShareImage('https://example.com/a.png'), type: ShareType::Article);

        self::assertSame(<<<'HTML'
            <link rel="canonical" href="https://example.com/">
            <meta property="og:title" content="Title">
            <meta property="og:type" content="article">
            <meta property="og:url" content="https://example.com/">
            <meta property="og:image" content="https://example.com/a.png">
            <meta name="twitter:card" content="summary">
            <meta name="twitter:title" content="Title">
            <meta name="twitter:image" content="https://example.com/a.png">
            HTML, MetaTags::html(MetaTags::of($page, TwitterCard::Summary)));
        self::assertSame('', MetaTags::html([]));
    }

    public function testATagEscapesItsKeyAndAnInvalidByte(): void
    {
        self::assertSame("<meta name=\"a&quot;b\" content=\"x\u{FFFD}y\">", MetaTag::name('a"b', "x\xFFy")->html());
        self::assertSame('<meta property="og:x" content="&apos;">', MetaTag::property('og:x', "'")->html());
        self::assertSame('<meta property="&apos;&lt;" content="x">', MetaTag::property("'<", 'x')->html());
        self::assertSame("<meta property=\"x\u{FFFD}\" content=\"x\">", MetaTag::property("x\xFF", 'x')->html());
        self::assertSame('<link rel="me" href="https://example.com/">', MetaTag::link('me', 'https://example.com/')->html());
    }
}
