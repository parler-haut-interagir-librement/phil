<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit;

use Closure;
use DateTime;
use DateTimeImmutable;
use Exception;
use Phil\SocialCards\Exception\InvalidShareMetadata;
use Phil\SocialCards\ShareImage;
use Phil\SocialCards\ShareMetadata;
use Phil\SocialCards\ShareType;
use Phil\SocialCards\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShareMetadata::class)]
#[CoversClass(ShareImage::class)]
#[CoversClass(Text::class)]
final class ShareMetadataTest extends TestCase
{
    public function testEveryValue(): void
    {
        $page = new ShareMetadata(
            "  Le jeu\tde\n rôle  ",
            ' https://example.com/fr/jeu ',
            "Une\u{0007}description",
            new ShareImage('https://example.com/jeu.png', '  Un dé ', 1200, 630, 'image/png'),
            ShareType::Article,
            'fr-ca',
            ['en_CA', 'en-ca', 'fr_CA', 'zh-hant-tw', 'es-419'],
            'Le site',
            'Une autrice',
            new DateTimeImmutable('2026-11-01T08:00:00+00:00'),
            new DateTimeImmutable('2026-11-02T08:00:00+00:00'),
            'site_',
            '@Creator',
            'gm@example.social',
        );

        self::assertSame(['Le jeu de rôle', 'https://example.com/fr/jeu', 'Une description'], [$page->title, $page->url, $page->description]);
        self::assertSame(['https://example.com/jeu.png', 'Un dé', 1200, 630, 'image/png'], [$page->image?->url, $page->image?->alt, $page->image?->width, $page->image?->height, $page->image?->type]);
        self::assertSame(['fr_CA', ['en_CA', 'zh_Hant_TW', 'es_419']], [$page->locale, $page->alternateLocales]);
        self::assertSame(['Le site', 'Une autrice', '@site_', '@Creator', '@gm@example.social'], [$page->siteName, $page->author, $page->twitterSite, $page->twitterCreator, $page->fediverseCreator]);
        self::assertSame(ShareType::Article, $page->type);
        self::assertSame('2026-11-02', $page->modifiedTime?->format('Y-m-d'));
    }

    public function testTheDefaults(): void
    {
        $page = new ShareMetadata('Title', 'http://example.com');

        self::assertSame([null, null, ShareType::Website, null, [], null, null, null, null, null, null, null], [$page->description, $page->image, $page->type, $page->locale, $page->alternateLocales, $page->siteName, $page->author, $page->publishedTime, $page->modifiedTime, $page->twitterSite, $page->twitterCreator, $page->fediverseCreator]);
        self::assertSame(['fr'], new ShareMetadata('T', 'https://example.com', alternateLocales: ['fr', 'FR'])->alternateLocales);
        self::assertSame(['fr'], new ShareMetadata('T', 'https://example.com', alternateLocales: array_fill(0, 50, 'fr'))->alternateLocales, 'Fifty alternates are allowed.');
    }

    public function testLongTextsAreCut(): void
    {
        $page = new ShareMetadata(str_repeat('é', 400), 'https://example.com', str_repeat('a ', 600).'end', siteName: str_repeat('s', 201), author: str_repeat('b', 200));

        self::assertSame(str_repeat('é', 299).'…', $page->title);
        self::assertSame(300, mb_strlen($page->title));
        self::assertSame(str_repeat('a ', 499).'a…', $page->description);
        self::assertSame(1000, mb_strlen($page->description));
        self::assertSame(str_repeat('s', 199).'…', $page->siteName);
        self::assertSame(str_repeat('b', 200), $page->author);
        self::assertSame(str_repeat('x', 299).'…', new ShareMetadata(str_repeat('x', 301), 'https://example.com')->title);
        self::assertSame(str_repeat('x', 300), new ShareMetadata(str_repeat('x', 300), 'https://example.com')->title);
        self::assertSame('ab…', Text::line('ab   cdef', 4), 'The text is cut, then its trailing blank removed.');
        self::assertSame('ééé', Text::line('ééé', 3), 'The length is counted in characters.');
        self::assertSame(str_repeat('é', 100), Text::excerpt(str_repeat('é', 101)));
        self::assertSame('a?b', Text::excerpt("a\xFFb"));
        self::assertSame('a?b', Text::line("a\xFFb", 10), 'An invalid byte is replaced.');
        self::assertSame('a b', Text::line("a\u{2028}\u{2029}\u{0085}b", 10));
        self::assertSame('moc.elpmaxe-evil', Text::line("\u{202E}moc.elpmaxe-evil\u{202C}", 30), 'The controls of the direction of the text are removed.');
        self::assertSame('ab cd', Text::line("a\u{200B}b\u{2066} \u{2069}c\u{00AD}d\u{FEFF}", 30), 'The invisible formatting characters are removed.');
        self::assertSame("\u{1F469}\u{200D}\u{1F4BB} \u{0645}\u{06CC}\u{200C}\u{062E}", Text::line("\u{1F469}\u{200D}\u{1F4BB} \u{0645}\u{06CC}\u{200C}\u{062E}", 30), 'The joiners that shape an emoji or a word are kept.');
        self::assertNull(Text::line("\u{200B}\u{202E}", 10));
    }

    public function testTheAddressesReadInAPageAreReadByPhilUrl(): void
    {
        self::assertSame('https://xn--exmple-cua.com/a', Text::publicUrl(' https://EXÄMPLE.com:443/a '));

        foreach (['http://127.0.0.1/', 'http://[::1]/', 'http://localhost/', 'http://a.localhost/', 'https://intranet/', 'https://u:p@example.com/', 'ftp://example.com/', '/relative'] as $url) {
            self::assertNull(Text::publicUrl($url), $url);
        }

        self::assertSame('https://example.com/b/c', Text::resolve('c', 'https://example.com/b/a'));
        self::assertNull(Text::resolve('http://10.0.0.1/c', 'https://example.com/'));
        self::assertTrue(Text::onSameHost('http://example.com/a', 'https://example.com/b'));
        self::assertFalse(Text::onSameHost('https://example.com/', 'https://www.example.com/'));
        self::assertFalse(Text::onSameHost('https://example.com/', 'https://example.com:8443/'));
        self::assertNull(Text::line(" \t\n", 10));
        self::assertSame('x', Text::line('x', 1));
    }

    public function testTheShortTextsOfAnImage(): void
    {
        $image = new ShareImage('https://example.com/a.png', str_repeat('a', 421), 1, 10000);

        self::assertSame(str_repeat('a', 419).'…', $image->alt);
        self::assertSame([1, 10000, null], [$image->width, $image->height, $image->type]);
        self::assertNull(new ShareImage('https://example.com/a.png', ' ')->alt);
        self::assertSame('image/svg+xml', new ShareImage('https://example.com/a.svg', type: 'image/svg+xml')->type);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function badPages(): iterable
    {
        $page = static fn (array $changes): Closure => static fn (): ShareMetadata => new ShareMetadata(...['title' => 'Title', 'url' => 'https://example.com/', ...$changes]);
        $image = static fn (string $url, ?int $width = null, ?int $height = null, ?string $type = null): Closure => static fn (): ShareImage => new ShareImage($url, null, $width, $height, $type);

        yield 'no title' => [$page(['title' => " \n"]), 'A shared page needs a title.'];
        yield 'a relative address' => [$page(['url' => '/page']), 'The address of the page must be an absolute HTTP or HTTPS address without credentials, of 2048 characters at most; "/page" is not.'];
        yield 'another scheme' => [$page(['url' => 'ftp://example.com/']), 'address of the page'];
        yield 'credentials' => [$page(['url' => 'https://user@example.com/']), 'address of the page'];
        yield 'a password' => [$page(['url' => 'https://:secret@example.com/']), 'address of the page'];
        yield 'a space' => [$page(['url' => 'https://example.com/a b']), 'address of the page'];
        yield 'a control' => [$page(['url' => "https://example.com/\u{0001}"]), 'address of the page'];
        yield 'not an address' => [$page(['url' => 'https://']), 'address of the page'];
        yield 'too long an address' => [$page(['url' => 'https://example.com/'.str_repeat('a', 2029)]), 'address of the page'];
        yield 'an invalid locale' => [$page(['locale' => 'french']), '"french" is not a locale: a language of 2 or 3 letters, maybe a script and a region, as fr_CA.'];
        yield 'an invalid alternate' => [$page(['alternateLocales' => ['fr', 'x']]), '"x" is not a locale'];
        yield 'too many alternates' => [$page(['alternateLocales' => array_fill(0, 51, 'fr')]), 'A page names 50 alternate locales at most; 51 are given.'];
        yield 'an account on X with a dot' => [$page(['twitterSite' => 'a.b']), '"a.b" is not an account on X: 1 to 15 letters, digits or underscores.'];
        yield 'too long an account on X' => [$page(['twitterCreator' => str_repeat('a', 16)]), 'is not an account on X'];
        yield 'an empty account on X' => [$page(['twitterSite' => '@']), 'is not an account on X'];
        yield 'an account on X with an accent' => [$page(['twitterSite' => 'é']), 'is not an account on X'];
        yield 'a fediverse account without a domain' => [$page(['fediverseCreator' => '@user']), '"user" is not an account on the fediverse: a user and a domain, as @user@example.social.'];
        yield 'a fediverse account on a bare host' => [$page(['fediverseCreator' => '@user@localhost']), 'not an account on the fediverse'];
        yield 'a fediverse account with a space' => [$page(['fediverseCreator' => '@us er@example.social']), 'not an account on the fediverse'];
        yield 'an image without an address' => [$image(''), 'The image address must be'];
        yield 'an image too narrow' => [$image('https://example.com/a.png', 0), 'The width of an image is 1 to 10000 pixels; 0 is not.'];
        yield 'an image too tall' => [$image('https://example.com/a.png', null, 10001), 'The height of an image is 1 to 10000 pixels; 10001 is not.'];
        yield 'an image type' => [$image('https://example.com/a.png', null, null, 'text/html'), '"text/html" is not the media type of an image.'];
        yield 'an image type with a newline' => [$image('https://example.com/a.png', null, null, "image/png\n"), 'is not the media type of an image'];
        yield 'an image type in capitals' => [$image('https://example.com/a.png', null, null, 'IMAGE/PNG'), 'is not the media type of an image'];
        yield 'too long an image type' => [$image('https://example.com/a.png', null, null, 'image/'.str_repeat('a', 65)), 'is not the media type of an image'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('badPages')]
    public function testABadPageIsRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidShareMetadata::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheEdgesOfAHandle(): void
    {
        self::assertSame('image/'.str_repeat('a', 64), new ShareImage('https://example.com/a.png', type: 'image/'.str_repeat('a', 64))->type);
        self::assertSame('@'.str_repeat('a', 15), ShareMetadata::twitterHandle(str_repeat('a', 15)));
        self::assertSame('@a', ShareMetadata::twitterHandle(' @a '));
        self::assertSame('@'.str_repeat('u', 64).'@a.b.social', ShareMetadata::fediverseHandle(str_repeat('u', 64).'@a.b.social'));
        self::assertSame('@u@x-1.social', ShareMetadata::fediverseHandle(' @u@x-1.social '));
        self::assertSame('@u@'.str_repeat('a', 63).'.'.str_repeat('b', 63), ShareMetadata::fediverseHandle('u@'.str_repeat('a', 63).'.'.str_repeat('b', 63)));

        foreach ([str_repeat('u', 65).'@a.social', 'u@-a.social', 'u@a-.social', 'u@a.s', 'u@a.'.str_repeat('s', 64), 'u@'.str_repeat('a', 64).'.social', 'u@a.social.', 'u@a.so1'] as $handle) {
            try {
                ShareMetadata::fediverseHandle($handle);
                self::fail(\sprintf('"%s" is refused.', $handle));
            } catch (InvalidShareMetadata) {
                self::addToAssertionCount(1);
            }
        }

        $exact = 'u@'.str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61);
        self::assertSame(255, \strlen($exact));
        self::assertSame('@'.$exact, ShareMetadata::fediverseHandle($exact));
        $long = 'u@'.implode('.', array_fill(0, 63, 'abc')).'.social';
        self::assertGreaterThan(255, \strlen($long));
        $this->expectException(InvalidShareMetadata::class);
        ShareMetadata::fediverseHandle($long);
    }

    public function testTheSpellingsOfALocale(): void
    {
        self::assertSame(['fr', 'fr_CA', 'zh_Hant', 'zh_Hant_TW', 'es_419', 'haw_US'], array_map(ShareMetadata::locale(...), ['FR', ' fr_ca ', 'ZH-HANT', 'zh_hant-tw', 'es-419', 'haw-us']));

        foreach (['f', 'fren', 'fr_C', 'fr_CAN', 'fr_41', 'fr__CA', 'fr_CA_x'] as $locale) {
            try {
                ShareMetadata::locale($locale);
                self::fail(\sprintf('"%s" is refused.', $locale));
            } catch (InvalidShareMetadata) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testFromAnArray(): void
    {
        $page = ShareMetadata::fromArray([
            'title' => 'Title',
            'url' => 'https://example.com/',
            'description' => 'Description',
            'image' => ['url' => 'https://example.com/a.png', 'alt' => 'Alt', 'width' => 1200, 'height' => 630, 'type' => 'image/png'],
            'type' => 'article',
            'locale' => 'fr',
            'alternate_locales' => ['en', 'es'],
            'site_name' => 'Site',
            'author' => 'Author',
            'published_time' => '2026-11-01T08:00:00+00:00',
            'modified_time' => new DateTimeImmutable('2026-11-03T08:00:00+00:00'),
            'twitter_site' => 'site',
            'twitter_creator' => 'creator',
            'fediverse_creator' => 'me@example.social',
        ]);

        self::assertSame(['Title', 'https://example.com/', 'Description', ShareType::Article, 'fr', ['en', 'es'], 'Site', 'Author'], [$page->title, $page->url, $page->description, $page->type, $page->locale, $page->alternateLocales, $page->siteName, $page->author]);
        self::assertSame(['https://example.com/a.png', 'Alt', 1200, 630, 'image/png'], [$page->image?->url, $page->image?->alt, $page->image?->width, $page->image?->height, $page->image?->type]);
        self::assertSame(['2026-11-01T08:00:00+00:00', '2026-11-03T08:00:00+00:00'], [$page->publishedTime?->format(\DATE_ATOM), $page->modifiedTime?->format(\DATE_ATOM)]);
        self::assertSame(['@site', '@creator', '@me@example.social'], [$page->twitterSite, $page->twitterCreator, $page->fediverseCreator]);

        $image = new ShareImage('https://example.com/b.png');
        $plain = ShareMetadata::fromArray(['title' => 'T', 'url' => 'https://example.com/', 'image' => $image, 'type' => ShareType::Profile, 'description' => null, 'published_time' => new DateTime('2026-11-01')]);
        self::assertSame([$image, ShareType::Profile, null], [$plain->image, $plain->type, $plain->description]);
        self::assertInstanceOf(DateTimeImmutable::class, $plain->publishedTime);
        self::assertSame('https://example.com/c.png', ShareMetadata::fromArray(['title' => 'T', 'url' => 'https://example.com/', 'image' => 'https://example.com/c.png'])->image?->url);
        self::assertSame([ShareType::Website, null, null], [($minimal = ShareMetadata::fromArray(['title' => 'T', 'url' => 'https://example.com/', 'image' => ['url' => 'https://example.com/d.png']]))->type, $minimal->image?->width, $minimal->image?->alt]);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function badArrays(): iterable
    {
        $page = ['title' => 'T', 'url' => 'https://example.com/'];

        yield 'an unknown key' => [[...$page, 'titel' => 'T', 'imgae' => 'x'], 'The share metadata has no key "titel", "imgae"; it reads title, url, description, image, type, locale, alternate_locales, site_name, author, published_time, modified_time, twitter_site, twitter_creator, fediverse_creator.'];
        yield 'an integer key' => [[...$page, 0 => 'T'], 'has no key "0"'];
        yield 'no title' => [['url' => 'https://example.com/'], 'A shared page needs a title.'];
        yield 'no address' => [['title' => 'T'], 'A shared page needs its address.'];
        yield 'a title that is no string' => [[...$page, 'title' => 3], 'The share metadata "title" is a string; int is given.'];
        yield 'an unknown type' => [[...$page, 'type' => 'blog'], 'The type of a shared page is one of "website", "article", "profile", "book", "video.other".'];
        yield 'a type that is no string' => [[...$page, 'type' => 1], 'The type of a shared page is one of'];
        yield 'alternates that are no list' => [[...$page, 'alternate_locales' => 'fr'], 'The share metadata "alternate_locales" is a list of strings.'];
        yield 'alternates with keys' => [[...$page, 'alternate_locales' => ['a' => 'fr']], 'is a list of strings'];
        yield 'alternates that are no strings' => [[...$page, 'alternate_locales' => ['fr', 1]], 'is a list of strings'];
        yield 'a date that is no date' => [[...$page, 'published_time' => 'someday'], 'The share metadata "published_time" is not a date PHP reads: "someday".'];
        yield 'a date that is no string' => [[...$page, 'modified_time' => 20261101], 'The share metadata "modified_time" is a date; int is given.'];
        yield 'an image that is a number' => [[...$page, 'image' => 3], 'The image of a shared page is an address, a ShareImage or an array; int is given.'];
        yield 'an image with an unknown key' => [[...$page, 'image' => ['url' => 'https://example.com/a.png', 'src' => 'x']], 'The image of a shared page has a string "url", "alt" and "type" and an integer "width" and "height".'];
        yield 'an image whose width is a string' => [[...$page, 'image' => ['url' => 'https://example.com/a.png', 'width' => '1200']], 'an integer "width"'];
        yield 'an image whose height is a string' => [[...$page, 'image' => ['url' => 'https://example.com/a.png', 'height' => '630']], 'an integer "width"'];
        yield 'an image without its address' => [[...$page, 'image' => ['alt' => 'A']], 'The image of a shared page needs its "url".'];
        yield 'an image whose alt is no string' => [[...$page, 'image' => ['url' => 'https://example.com/a.png', 'alt' => 1]], 'The share metadata "alt" is a string; int is given.'];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    #[DataProvider('badArrays')]
    public function testABadArrayIsRefused(array $values, string $message): void
    {
        $this->expectException(InvalidShareMetadata::class);
        $this->expectExceptionMessageIsOrContains($message);

        ShareMetadata::fromArray($values);
    }

    public function testAnUnreadableDateKeepsItsCause(): void
    {
        try {
            ShareMetadata::fromArray(['title' => 'T', 'url' => 'https://example.com/', 'published_time' => 'someday']);
            self::fail('The date is refused.');
        } catch (InvalidShareMetadata $invalidShareMetadata) {
            self::assertInstanceOf(Exception::class, $invalidShareMetadata->getPrevious());
            self::assertSame(0, $invalidShareMetadata->getCode());
        }
    }

    public function testAddressesReadInAPage(): void
    {
        self::assertSame('https://example.com/a/img.png', Text::resolve(' img.png ', 'https://example.com/a/b'));
        self::assertSame('https://cdn.example.com/x.png', Text::resolve('//cdn.example.com/x.png', 'https://example.com/'));
        self::assertSame('https://other.example/x', Text::resolve('https://other.example/x', 'not an address'));
        self::assertNull(Text::resolve('img.png', 'not an address'));
        self::assertNull(Text::resolve('javascript:alert(1)', 'https://example.com/'));
        self::assertNull(Text::resolve(null, 'https://example.com/'));
        self::assertNull(Text::resolve(' ', 'https://example.com/'));
        self::assertNull(Text::resolve('https://[', 'https://example.com/'));
        self::assertNull(Text::resolve('https://user:pw@example.com/', 'https://example.com/'));
        self::assertTrue(Text::isAbsoluteUrl('https://example.com/'.str_repeat('a', 2028)));
        self::assertFalse(Text::isAbsoluteUrl(''));
        self::assertFalse(Text::isAbsoluteUrl("https://example.com/a\u{00A0}b"), 'A Unicode blank is refused.');
        self::assertFalse(Text::isAbsoluteUrl("https://example.com/a\u{0085}b"), 'A Unicode control is refused.');
        self::assertFalse(Text::isAbsoluteUrl("https://example.com/\xFF"), 'An address is valid UTF-8.');
    }
}
