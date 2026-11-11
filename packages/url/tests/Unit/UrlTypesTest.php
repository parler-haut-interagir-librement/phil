<?php

declare(strict_types=1);

namespace Phil\Url\Tests\Unit;

use Phil\Enums\Collection\EnumSet;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\KnownUrlType;
use Phil\Url\Tests\Unit\Fixtures\RegistryUrlType;
use Phil\Url\TypedUrl;
use Phil\Url\Url;
use Phil\Url\UrlTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(KnownUrlType::class)]
#[CoversClass(TypedUrl::class)]
#[CoversClass(UrlTypes::class)]
final class UrlTypesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, KnownUrlType}>
     */
    public static function known(): iterable
    {
        yield 'facebook' => ['https://www.facebook.com/shop', KnownUrlType::Facebook];
        yield 'facebook short' => ['https://fb.me/shop', KnownUrlType::Facebook];
        yield 'fb.com' => ['https://fb.com/shop', KnownUrlType::Facebook];
        yield 'instagram' => ['https://instagram.com/shop', KnownUrlType::Instagram];
        yield 'instagr.am' => ['https://instagr.am/shop', KnownUrlType::Instagram];
        yield 'x' => ['https://x.com/shop', KnownUrlType::X];
        yield 'twitter' => ['https://mobile.twitter.com/shop', KnownUrlType::X];
        yield 'threads' => ['https://www.threads.net/@shop', KnownUrlType::Threads];
        yield 'threads.com' => ['https://threads.com/@shop', KnownUrlType::Threads];
        yield 'bluesky' => ['https://bsky.app/profile/shop.bsky.social', KnownUrlType::Bluesky];
        yield 'linkedin' => ['https://ca.linkedin.com/company/shop', KnownUrlType::LinkedIn];
        yield 'lnkd.in' => ['https://lnkd.in/abc', KnownUrlType::LinkedIn];
        yield 'tiktok' => ['https://www.tiktok.com/@shop', KnownUrlType::TikTok];
        yield 'youtube' => ['https://www.youtube.com/@shop', KnownUrlType::YouTube];
        yield 'youtu.be' => ['https://youtu.be/abc', KnownUrlType::YouTube];
        yield 'youtube-nocookie' => ['https://www.youtube-nocookie.com/embed/abc', KnownUrlType::YouTube];
        yield 'vimeo' => ['https://vimeo.com/123', KnownUrlType::Vimeo];
        yield 'twitch' => ['https://www.twitch.tv/shop', KnownUrlType::Twitch];
        yield 'pinterest' => ['https://www.pinterest.com/shop', KnownUrlType::Pinterest];
        yield 'pinterest.ca' => ['https://pinterest.ca/shop', KnownUrlType::Pinterest];
        yield 'pinterest.co.uk' => ['https://uk.pinterest.co.uk/shop', KnownUrlType::Pinterest];
        yield 'pinterest.com.mx' => ['https://pinterest.com.mx/shop', KnownUrlType::Pinterest];
        yield 'pinterest.jp' => ['https://www.pinterest.jp/shop', KnownUrlType::Pinterest];
        yield 'pin.it' => ['https://pin.it/abc', KnownUrlType::Pinterest];
        yield 'reddit' => ['https://www.reddit.com/r/tea', KnownUrlType::Reddit];
        yield 'redd.it' => ['https://redd.it/abc', KnownUrlType::Reddit];
        yield 'tumblr' => ['https://shop.tumblr.com/', KnownUrlType::Tumblr];
        yield 'flickr' => ['https://www.flickr.com/photos/shop', KnownUrlType::Flickr];
        yield 'flic.kr' => ['https://flic.kr/p/abc', KnownUrlType::Flickr];
        yield 'discord' => ['https://discord.gg/abc', KnownUrlType::Discord];
        yield 'discord.com' => ['https://discord.com/invite/abc', KnownUrlType::Discord];
        yield 'github' => ['https://github.com/shop', KnownUrlType::GitHub];
        yield 'spotify' => ['https://open.spotify.com/artist/abc', KnownUrlType::Spotify];
        yield 'spotify.link' => ['https://spotify.link/abc', KnownUrlType::Spotify];
        yield 'soundcloud' => ['https://soundcloud.com/shop', KnownUrlType::SoundCloud];
        yield 'bandcamp' => ['https://shop.bandcamp.com/', KnownUrlType::Bandcamp];
        yield 'patreon' => ['https://www.patreon.com/shop', KnownUrlType::Patreon];
        yield 'kickstarter' => ['https://www.kickstarter.com/projects/shop', KnownUrlType::Kickstarter];
        yield 'kck.st' => ['https://kck.st/abc', KnownUrlType::Kickstarter];
        yield 'etsy' => ['https://www.etsy.com/shop/abc', KnownUrlType::Etsy];
        yield 'etsy.me' => ['https://etsy.me/abc', KnownUrlType::Etsy];
        yield 'linktree' => ['https://linktr.ee/shop', KnownUrlType::Linktree];
        yield 'wikipedia' => ['https://fr.wikipedia.org/wiki/Th%C3%A9', KnownUrlType::Wikipedia];
        yield 'mastodon' => ['https://mastodon.social/@shop', KnownUrlType::Mastodon];
        yield 'mastodon from elsewhere' => ['https://example.social/@shop@mastodon.social', KnownUrlType::Mastodon];
        yield 'a website' => ['https://example.com/', KnownUrlType::Website];
        yield 'a page that looks like a profile' => ['https://example.com/@shop/posts', KnownUrlType::Website];
        yield 'a pinterest look-alike' => ['https://pinterest.example.com/', KnownUrlType::Website];
        yield 'pinterest on a domain anyone can register' => ['https://pinterest.xyz/shop', KnownUrlType::Website];
        yield 'pinterest in a public second-level zone' => ['https://pinterest.com.ru/shop', KnownUrlType::Website];
        yield 'pinterest on a country Pinterest does not own' => ['https://www.pinterest.biz/shop', KnownUrlType::Website];
        yield 'the images of pinterest are no profile' => ['https://i.pinimg.com/abc.jpg', KnownUrlType::Website];
        yield 'a long name is no profile' => ['https://example.social/@'.str_repeat('a', 31), KnownUrlType::Website];
        yield 'a strange name is no profile' => ['https://example.social/@ad-a', KnownUrlType::Website];
        yield 'a profile on an odd server' => ['https://example.social/@ada@Mastodon.social', KnownUrlType::Website];
        yield 'a profile and a newline' => ['https://example.social/@ada%0A', KnownUrlType::Website];
        yield 'something before the name' => ['https://example.social/x@ada', KnownUrlType::Website];
        yield 'the root' => ['https://example.social/', KnownUrlType::Website];
        yield 'a medium profile' => ['https://medium.com/@ada', KnownUrlType::Website];
        yield 'a substack profile' => ['https://substack.com/@ada', KnownUrlType::Website];
        yield 'a figma profile' => ['https://www.figma.com/@ada', KnownUrlType::Website];
    }

    #[DataProvider('known')]
    public function testTheKindOfAnAddressIsDetected(string $text, KnownUrlType $type): void
    {
        self::assertSame($type, UrlTypes::detect(Url::fromString($text), EnumSet::all(KnownUrlType::class)));
    }

    /**
     * The profiles of the other kinds are no Mastodon profiles, even in a set without their own kind.
     */
    public function testAnotherPlatformsProfileIsNoMastodonProfile(): void
    {
        $set = EnumSet::of(KnownUrlType::class, KnownUrlType::Mastodon, KnownUrlType::Website);

        foreach (['https://www.youtube.com/@ada', 'https://www.tiktok.com/@ada', 'https://www.threads.net/@ada', 'https://medium.com/@ada', 'https://pinterest.ca/@ada', 'https://ada.medium.com/@ada'] as $text) {
            self::assertFalse(KnownUrlType::Mastodon->accepts(Url::fromString($text)), $text);
            self::assertSame(KnownUrlType::Website, UrlTypes::detect(Url::fromString($text), $set), $text);
        }

        self::assertSame(KnownUrlType::Mastodon, UrlTypes::detect(Url::fromString('https://mastodon.social/@ada'), $set));
    }

    public function testAMastodonNameHas30CharactersAtMost(): void
    {
        self::assertTrue(KnownUrlType::Mastodon->accepts(Url::fromString('https://example.social/@'.str_repeat('a', 30))));
        self::assertTrue(KnownUrlType::Mastodon->accepts(Url::fromString('https://example.social/@A_1')));
    }

    public function testEveryInternationalKindHasItsLabel(): void
    {
        self::assertSame('Facebook', KnownUrlType::Facebook->label());
        self::assertSame('X (Twitter)', KnownUrlType::X->label());
        self::assertSame('YouTube', KnownUrlType::YouTube->label());
        self::assertSame('LinkedIn', KnownUrlType::LinkedIn->label());
        self::assertSame('TikTok', KnownUrlType::TikTok->label());
        self::assertSame('GitHub', KnownUrlType::GitHub->label());
        self::assertSame('SoundCloud', KnownUrlType::SoundCloud->label());
        self::assertSame('Website', KnownUrlType::Website->label());
        self::assertSame(KnownUrlType::Website, KnownUrlType::cases()[\count(KnownUrlType::cases()) - 1]);
        self::assertSame('instagram', KnownUrlType::Instagram->value);
    }

    public function testOnlyTheAcceptedKindsAreDetected(): void
    {
        $social = EnumSet::of(KnownUrlType::class, KnownUrlType::Facebook, KnownUrlType::Instagram);

        self::assertSame(KnownUrlType::Instagram, UrlTypes::detect(Url::fromString('https://instagram.com/shop'), $social));
        self::assertNull(UrlTypes::detect(Url::fromString('https://example.com/'), $social));
        self::assertNull(UrlTypes::detect(Url::fromString('https://example.com/')));
    }

    public function testAnApplicationsKindsComeBeforeOrAfterTheInternationalOnes(): void
    {
        $listing = Url::fromString('https://registry.example/listing/42');
        $own = EnumSet::all(RegistryUrlType::class);
        $known = EnumSet::all(KnownUrlType::class);

        self::assertSame(RegistryUrlType::Listing, UrlTypes::detect($listing, $own, $known));
        self::assertSame(RegistryUrlType::Registry, UrlTypes::detect(Url::fromString('https://registry.example/about'), $own, $known));
        self::assertSame(KnownUrlType::Website, UrlTypes::detect($listing, $known, $own));
        self::assertSame(KnownUrlType::Facebook, UrlTypes::detect(Url::fromString('https://facebook.com/x'), $own, $known));
    }

    public function testAnAddressIsTypedOrRefused(): void
    {
        $typed = UrlTypes::type('https://instagram.com/shop', EnumSet::all(KnownUrlType::class));

        self::assertSame([KnownUrlType::Instagram, 'https://instagram.com/shop'], [$typed->type, $typed->url->toString()]);
        self::assertSame(KnownUrlType::Website, UrlTypes::type(Url::fromString('https://example.com'), EnumSet::all(KnownUrlType::class))->type);

        try {
            UrlTypes::type('https://example.com/', EnumSet::of(KnownUrlType::class, KnownUrlType::Facebook, KnownUrlType::X), EnumSet::of(RegistryUrlType::class, RegistryUrlType::Registry));
            self::fail('An address of no accepted kind was typed.');
        } catch (InvalidUrl $invalidUrl) {
            self::assertSame('https://example.com/ is not a Facebook, X, Registry address.', $invalidUrl->getMessage());
        }
    }

    public function testATypedUrlIsOfItsKind(): void
    {
        $typed = TypedUrl::of(KnownUrlType::Facebook, 'https://facebook.com/shop');

        self::assertTrue($typed->equals(TypedUrl::of(KnownUrlType::Facebook, 'HTTPS://Facebook.com/shop')));
        self::assertFalse($typed->equals(TypedUrl::of(KnownUrlType::Website, 'https://facebook.com/shop')));
        self::assertFalse($typed->equals(TypedUrl::of(KnownUrlType::Facebook, 'https://facebook.com/other')));

        $this->expectException(InvalidUrl::class);
        $this->expectExceptionMessageIsOrContains('https://example.com/ is not a Instagram address.');

        TypedUrl::of(KnownUrlType::Instagram, 'https://example.com');
    }
}
