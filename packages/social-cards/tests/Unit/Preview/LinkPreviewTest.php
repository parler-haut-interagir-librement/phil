<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Preview;

use Phil\SocialCards\Preview\LinkPreview;
use Phil\SocialCards\Preview\PageTags;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkPreview::class)]
final class LinkPreviewTest extends TestCase
{
    public function testOpenGraphFirst(): void
    {
        $preview = LinkPreview::fromTags(new PageTags('https://example.com/a/b', 'Plain title', 'https://example.com/canonical', [
            'og:url' => ['/og'],
            'og:title' => ['OG title'],
            'twitter:title' => ['X title'],
            'og:description' => ['OG description'],
            'twitter:description' => ['X description'],
            'description' => ['Plain description'],
            'og:image' => ['img.png'],
            'twitter:image' => ['https://example.com/x.png'],
            'og:image:alt' => ['OG alt'],
            'twitter:image:alt' => ['X alt'],
            'og:site_name' => ['The site'],
            'og:type' => ['article'],
            'og:locale' => ['fr_CA'],
        ]));

        self::assertSame(['url' => 'https://example.com/og', 'title' => 'OG title', 'description' => 'OG description', 'image' => 'https://example.com/a/img.png', 'image_alt' => 'OG alt', 'site_name' => 'The site', 'type' => 'article', 'locale' => 'fr_CA'], $preview->toArray());
        self::assertEquals($preview, LinkPreview::fromArray($preview->toArray()));
    }

    public function testTheFallbacks(): void
    {
        $x = LinkPreview::fromTags(new PageTags('https://exämple.com/a', 'Plain title', null, [
            'twitter:title' => ['X title'],
            'twitter:description' => ['X description'],
            'twitter:image:src' => ['/x.png'],
            'twitter:image:alt' => ['X alt'],
        ]));
        self::assertSame(['url' => 'https://xn--exmple-cua.com/a', 'title' => 'X title', 'description' => 'X description', 'image' => 'https://xn--exmple-cua.com/x.png', 'image_alt' => 'X alt', 'site_name' => 'exämple.com', 'type' => null, 'locale' => null], $x->toArray());

        $plain = LinkPreview::fromTags(new PageTags('https://example.com/a', 'Plain title', 'https://example.com/canonical', ['description' => ['Plain'], 'og:image:url' => ['/u.png'], 'og:url' => ['javascript:x']]));
        self::assertSame(['https://example.com/canonical', 'Plain title', 'Plain', 'https://example.com/u.png', null, 'example.com'], [$plain->url, $plain->title, $plain->description, $plain->image, $plain->imageAlt, $plain->siteName]);

        self::assertSame(['not an address', 'not an address'], [LinkPreview::fromTags(new PageTags('not an address'))->title, LinkPreview::fromTags(new PageTags('not an address'))->siteName]);
        $bare = LinkPreview::fromTags(new PageTags('https://example.com/a'));
        self::assertSame(['https://example.com/a', 'example.com', null, null, 'example.com'], [$bare->url, $bare->title, $bare->description, $bare->image, $bare->siteName]);
        self::assertSame('https://example.com/s.png', LinkPreview::fromTags(new PageTags('https://example.com/', meta: ['og:image:secure_url' => ['/s.png'], 'twitter:image' => ['/t.png']]))->image);
        self::assertSame('https://example.com/t.png', LinkPreview::fromTags(new PageTags('https://example.com/', meta: ['twitter:image' => ['/t.png'], 'twitter:image:src' => ['/u.png']]))->image);
    }

    public function testTheAddressIsThePageReadUnlessTheTagsNameTheSameHost(): void
    {
        $page = 'https://evil.example/post';

        foreach ([
            'another host in og:url' => new PageTags($page, meta: ['og:url' => ['https://bank.example/login']]),
            'another host as canonical' => new PageTags($page, canonical: 'https://bank.example/login'),
            'a subdomain in og:url' => new PageTags($page, meta: ['og:url' => ['https://www.evil.example/post']]),
            'another port in og:url' => new PageTags($page, meta: ['og:url' => ['https://evil.example:8443/post']]),
            'an IP address in og:url' => new PageTags($page, meta: ['og:url' => ['http://127.0.0.1/']]),
        ] as $case => $tags) {
            $preview = LinkPreview::fromTags($tags);

            self::assertSame([$page, 'evil.example', 'evil.example'], [$preview->url, $preview->title, $preview->siteName], $case);
        }

        self::assertSame('https://evil.example/canonical', LinkPreview::fromTags(new PageTags($page, canonical: 'https://evil.example/canonical', meta: ['og:url' => ['https://bank.example/']]))->url);
        self::assertSame('http://evil.example/og', LinkPreview::fromTags(new PageTags($page, canonical: 'https://evil.example/canonical', meta: ['og:url' => ['http://evil.example/og']]))->url);
        self::assertSame('https://evil.example/post?x', LinkPreview::fromTags(new PageTags('https://Evil.Example/post?x', meta: ['og:url' => ['https://bank.example/']]))->url);
        self::assertSame('http://localhost/a', LinkPreview::fromTags(new PageTags('http://localhost/a', canonical: 'http://localhost/b', meta: ['og:url' => ['/c']]))->url);
    }

    public function testAnImageOnAnIpAddressOrOnLocalhostIsDropped(): void
    {
        foreach (['http://127.0.0.1/a.png', 'http://[::1]/a.png', 'http://localhost/a.png', 'https://intranet/a.png', 'https://user@example.com/a.png'] as $image) {
            self::assertNull(LinkPreview::fromTags(new PageTags('https://example.com/', meta: ['og:image' => [$image]]))->image, $image);
        }
    }

    public function testTheTextsAreBounded(): void
    {
        $preview = LinkPreview::fromTags(new PageTags('https://example.com/', meta: [
            'og:title' => [str_repeat('t', 400)],
            'og:description' => [str_repeat('d', 600)],
            'og:image:alt' => [str_repeat('a', 500)],
            'og:site_name' => [str_repeat('s', 300)],
            'og:type' => [str_repeat('y', 60)],
            'og:locale' => [str_repeat('l', 30)],
        ]));

        self::assertSame([300, 500, 420, 200, 50, 20], array_map(mb_strlen(...), [$preview->title, (string) $preview->description, (string) $preview->imageAlt, (string) $preview->siteName, (string) $preview->type, (string) $preview->locale]));
    }

    public function testFromAKeptArray(): void
    {
        self::assertNull(LinkPreview::fromArray([]));
        self::assertNull(LinkPreview::fromArray(['url' => 'https://example.com/', 'title' => 1]));
        self::assertNull(LinkPreview::fromArray(['url' => 1, 'title' => 'T']));
        self::assertNull(LinkPreview::fromArray(['url' => '/relative', 'title' => 'T']));
        self::assertNull(LinkPreview::fromArray(['url' => 'http://127.0.0.1/', 'title' => 'T']));
        self::assertNull(LinkPreview::fromArray(['url' => 'https://example.com/', 'title' => 'T', 'image' => 'http://localhost/i.png'])?->image);

        $preview = LinkPreview::fromArray(['url' => 'https://example.com/', 'title' => 'T', 'description' => 3, 'image' => 'javascript:x', 'site_name' => 'S']);
        self::assertNotNull($preview);
        self::assertSame(['url' => 'https://example.com/', 'title' => 'T', 'description' => null, 'image' => null, 'image_alt' => null, 'site_name' => 'S', 'type' => null, 'locale' => null], $preview->toArray());
        self::assertSame('https://example.com/i.png', LinkPreview::fromArray(['url' => 'https://example.com/', 'title' => 'T', 'image' => 'https://example.com/i.png'])?->image);
    }
}
