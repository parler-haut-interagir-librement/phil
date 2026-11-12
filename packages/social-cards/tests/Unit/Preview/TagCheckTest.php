<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Unit\Preview;

use Phil\SocialCards\Preview\Finding;
use Phil\SocialCards\Preview\PageTags;
use Phil\SocialCards\Preview\Severity;
use Phil\SocialCards\Preview\TagCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TagCheck::class)]
#[CoversClass(Finding::class)]
final class TagCheckTest extends TestCase
{
    private const array GOOD = [
        'og:title' => ['Title'],
        'og:type' => ['website'],
        'og:url' => ['https://example.com/page'],
        'og:image' => ['https://example.com/card.png'],
        'og:image:alt' => ['What the card shows'],
        'og:image:width' => ['1200'],
        'og:image:height' => ['630'],
        'og:description' => ['A description'],
        'twitter:card' => ['summary_large_image'],
        'fediverse:creator' => ['@me@example.social'],
    ];

    public function testAGoodPage(): void
    {
        $findings = TagCheck::check(new PageTags('https://example.com/page', canonical: 'https://example.com/page/', meta: self::GOOD));

        self::assertSame([], $findings);
        self::assertFalse(TagCheck::hasErrors($findings));
        self::assertSame([], TagCheck::check(new PageTags('https://example.com/', meta: [...self::GOOD, 'twitter:card' => ['player'], 'og:image:alt' => [], 'twitter:image:alt' => ['Alt']])));
        self::assertSame([], TagCheck::check(new PageTags('https://example.com/', meta: [...self::GOOD, 'twitter:card' => ['app'], 'og:image:width' => ['200'], 'og:image:height' => ['200'], 'og:title' => [str_repeat('é', 90)], 'og:description' => [str_repeat('é', 200)]])));
        self::assertSame([], TagCheck::check(new PageTags('https://example.com/', canonical: 'https://example.com/page', meta: [...self::GOOD, 'og:url' => ['https://example.com/page/']])), 'A trailing slash makes no other page.');
    }

    public function testAnEmptyPage(): void
    {
        $findings = TagCheck::check(new PageTags('https://example.com/'));

        self::assertSame([
            ['error', 'og:title', 'The page has no og:title, which Open Graph requires.'],
            ['error', 'og:type', 'The page has no og:type, which Open Graph requires.'],
            ['error', 'og:url', 'The page has no og:url, which Open Graph requires.'],
            ['error', 'og:image', 'The page has no og:image, which Open Graph requires.'],
            ['warning', 'og:description', 'The page has no og:description: readers show a link without a summary.'],
            ['warning', 'twitter:card', 'The page has no twitter:card: X shows the link as a small summary.'],
        ], $this->flat($findings));
        self::assertTrue(TagCheck::hasErrors($findings));
        self::assertFalse(TagCheck::hasErrors([new Finding(Severity::Warning, 'x', 'y')]));
    }

    public function testEveryMistake(): void
    {
        $findings = TagCheck::check(new PageTags('https://example.com/', canonical: 'https://example.com/canonical', meta: [
            ...self::GOOD,
            'og:url' => ['/page'],
            'og:image' => ['card.png'],
            'og:title' => [str_repeat('é', 91)],
            'og:description' => [str_repeat('é', 201)],
            'og:image:alt' => [],
            'og:image:width' => ['199'],
            'twitter:card' => ['gallery'],
            'fediverse:creator' => ['me@example.social'],
        ]));

        self::assertSame([
            ['error', 'og:url', 'The og:url "/page" is not an absolute HTTP or HTTPS address.'],
            ['error', 'og:image', 'The og:image "card.png" is not an absolute HTTP or HTTPS address.'],
            ['warning', 'og:title', 'The og:title has 91 characters; most readers cut it at 90.'],
            ['warning', 'og:description', 'The og:description has 201 characters; most readers cut it at 200.'],
            ['warning', 'og:image:alt', 'The image has no og:image:alt: those who cannot see it are not told what it shows.'],
            ['warning', 'og:image:width', 'The image is declared 199×630; readers show images of 200×200 pixels at least.'],
            ['error', 'twitter:card', '"gallery" is not a card of X: summary, summary_large_image, app or player.'],
            ['warning', 'og:url', 'The og:url "/page" is not the canonical link "https://example.com/canonical".'],
            ['warning', 'fediverse:creator', '"me@example.social" is not an account on the fediverse, as @user@example.social.'],
        ], $this->flat($findings));
    }

    public function testTheSizeOfTheImage(): void
    {
        $size = static fn (array $meta): array => array_column(array_map(static fn (Finding $finding): array => (array) $finding, TagCheck::check(new PageTags('https://example.com/', meta: [...self::GOOD, ...$meta]))), 'message');

        self::assertSame(['The image has no og:image:width and og:image:height: readers must fetch it before they draw the card.'], $size(['og:image:width' => []]));
        self::assertSame(['The image has no og:image:width and og:image:height: readers must fetch it before they draw the card.'], $size(['og:image:height' => []]));
        self::assertSame(['The image is declared 1200×199; readers show images of 200×200 pixels at least.'], $size(['og:image:height' => ['199']]));
        self::assertSame(['The image is declared 1200px×630; readers show images of 200×200 pixels at least.'], $size(['og:image:width' => ['1200px']]));
        self::assertSame(['The image is declared 1200×-630; readers show images of 200×200 pixels at least.'], $size(['og:image:height' => ['-630']]));
        self::assertSame(['The image is declared wide×630; readers show images of 200×200 pixels at least.'], $size(['og:image:width' => ['wide']]));
        self::assertSame(['The image is declared 1200×tall; readers show images of 200×200 pixels at least.'], $size(['og:image:height' => ['tall']]));
        self::assertSame(['"@me" is not an account on the fediverse, as @user@example.social.'], $size(['fediverse:creator' => ['@me']]));
        self::assertSame([], $size(['og:image' => [], 'og:image:width' => [], 'og:image:alt' => []])[1] ?? [], 'Without an image, nothing is said of its size or text.');
        self::assertCount(1, $size(['og:image' => [], 'og:image:width' => [], 'og:image:alt' => []]));
    }

    /**
     * @param list<Finding> $findings
     *
     * @return list<array{string, string, string}>
     */
    private function flat(array $findings): array
    {
        return array_map(static fn (Finding $finding): array => [$finding->severity->value, $finding->tag, $finding->message], $findings);
    }
}
