<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\DataMapper;

use Override;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;
use Phil\ContentBundle\Application\DataMapper\BodyDataMapper;
use Phil\ContentBundle\Application\DataMapper\ExcerptDataMapper;
use Phil\ContentBundle\Application\DataMapper\LinkDataMapper;
use Phil\ContentBundle\Application\DataMapper\RoutableDataMapper;
use Phil\ContentBundle\Application\DataMapper\SeoDataMapper;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\EchoMarkupCompiler;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Fixture\MarkingHtmlPurifier;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\MarkupFormat;
use Phil\Slug\Slug;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where each data mapper writes — the unlocalized row or the localized one —, what an absent key, a `null`
 * and a blank string do, and what is refused.
 */
#[CoversClass(SeoDataMapper::class)]
#[CoversClass(ExcerptDataMapper::class)]
#[CoversClass(LinkDataMapper::class)]
#[CoversClass(RoutableDataMapper::class)]
#[CoversClass(BodyDataMapper::class)]
final class CapabilityDataMappersTest extends TestCase
{
    private ExampleDimensionContent $unlocalized;

    private ExampleDimensionContent $localized;

    protected function setUp(): void
    {
        $root = new ExampleRoot();
        $this->unlocalized = $root->createDimensionContent(DimensionKey::unlocalized(Stage::Draft, Version::first()));
        $this->localized = $root->createDimensionContent(DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
    }

    public function testSeoIsWrittenOnTheLocalizedRow(): void
    {
        new SeoDataMapper()->map($this->unlocalized, $this->localized, [
            'seoTitle' => ' Bonjour ',
            'seoDescription' => 'Une description',
            'seoKeywords' => 'larp, events',
            'seoCanonicalUrl' => 'https://example.org/fr',
            'seoNoIndex' => true,
            'seoNoFollow' => false,
            'seoHideInSitemap' => true,
        ]);

        self::assertSame('Bonjour', $this->localized->getSeoTitle());
        self::assertSame('Une description', $this->localized->getSeoDescription());
        self::assertSame(['larp', 'events'], $this->localized->getSeoKeywords());
        self::assertSame('https://example.org/fr', $this->localized->getSeoCanonicalUrl()?->toString());
        self::assertTrue($this->localized->getSeoNoIndex());
        self::assertFalse($this->localized->getSeoNoFollow());
        self::assertTrue($this->localized->getSeoHideInSitemap());
        self::assertNull($this->unlocalized->getSeoTitle());
        self::assertNull($this->unlocalized->getSeoNoIndex());
    }

    public function testAnAbsentSeoKeyChangesNothingAndNullClears(): void
    {
        $this->localized->setSeoTitle('kept');
        $this->localized->setSeoDescription('cleared');
        $this->localized->setSeoKeywords(['cleared']);
        $this->localized->setSeoCanonicalUrl(Url::fromString('https://example.org/'));
        $this->localized->setSeoNoIndex(true);
        $this->localized->setSeoNoFollow(true);
        $this->localized->setSeoHideInSitemap(true);

        new SeoDataMapper()->map($this->unlocalized, $this->localized, [
            'seoDescription' => '',
            'seoKeywords' => null,
            'seoCanonicalUrl' => null,
            'seoNoIndex' => null,
            'seoHideInSitemap' => null,
        ]);

        self::assertSame('kept', $this->localized->getSeoTitle());
        self::assertNull($this->localized->getSeoDescription());
        self::assertSame([], $this->localized->getSeoKeywords());
        self::assertNull($this->localized->getSeoCanonicalUrl());
        self::assertNull($this->localized->getSeoNoIndex());
        self::assertTrue($this->localized->getSeoNoFollow());
        self::assertNull($this->localized->getSeoHideInSitemap());
    }

    public function testACanonicalUrlThatIsNotAbsoluteIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('is not an http or https address');

        new SeoDataMapper()->map($this->unlocalized, $this->localized, ['seoCanonicalUrl' => '/news/one']);
    }

    public function testTheExcerptTextIsLocalizedAndItsImageShared(): void
    {
        new ExcerptDataMapper()->map($this->unlocalized, $this->localized, [
            'excerptTitle' => 'Bonjour',
            'excerptDescription' => 'Une description',
            'excerptImage' => '/images/cover.png',
        ]);

        self::assertSame('Bonjour', $this->localized->getExcerptTitle());
        self::assertSame('Une description', $this->localized->getExcerptDescription());
        self::assertNull($this->localized->getExcerptImage());
        self::assertSame('/images/cover.png', $this->unlocalized->getExcerptImage());
        self::assertNull($this->unlocalized->getExcerptTitle());
    }

    public function testAnAbsentExcerptKeyChangesNothingAndNullClears(): void
    {
        $this->localized->setExcerptTitle('kept');
        $this->localized->setExcerptDescription('cleared');

        $this->unlocalized->setExcerptImage('/kept.png');

        new ExcerptDataMapper()->map($this->unlocalized, $this->localized, ['excerptDescription' => null]);

        self::assertSame('kept', $this->localized->getExcerptTitle());
        self::assertNull($this->localized->getExcerptDescription());
        self::assertSame('/kept.png', $this->unlocalized->getExcerptImage());

        new ExcerptDataMapper()->map($this->unlocalized, $this->localized, ['excerptImage' => null, 'excerptTitle' => null]);

        self::assertNull($this->unlocalized->getExcerptImage());
        self::assertNull($this->localized->getExcerptTitle());
    }

    public function testALinkToAContentRootIsWrittenOnTheLocalizedRow(): void
    {
        new LinkDataMapper()->map($this->unlocalized, $this->localized, [
            'link' => ['resourceKey' => 'article', 'resourceId' => '42', 'locale' => 'fr_CA'],
        ]);

        $link = $this->localized->getLink();
        self::assertInstanceOf(Link::class, $link);
        self::assertSame('article', $link->resourceKey);
        self::assertSame('42', $link->resourceId);
        self::assertSame('fr_CA', $link->locale?->value);
        self::assertNull($this->unlocalized->getLink());
    }

    /**
     * The normalized shape, with the keys of the other kind set to null, is read back as it was given.
     */
    public function testALinkToAUrlIsReadFromTheNormalizedShape(): void
    {
        new LinkDataMapper()->map($this->unlocalized, $this->localized, [
            'link' => ['resourceKey' => null, 'resourceId' => null, 'locale' => null, 'url' => 'https://example.org/'],
        ]);

        self::assertSame('https://example.org/', $this->localized->getLink()?->url?->toString());
    }

    public function testALinkWithoutALocaleFollowsTheReader(): void
    {
        new LinkDataMapper()->map($this->unlocalized, $this->localized, ['link' => ['resourceKey' => 'page', 'resourceId' => '7']]);

        self::assertNull($this->localized->getLink()?->locale);
    }

    public function testAnAbsentLinkChangesNothingAndNullClears(): void
    {
        $this->localized->setLink(Link::toContent('article', '42'));

        new LinkDataMapper()->map($this->unlocalized, $this->localized, []);
        self::assertSame('42', $this->localized->getLink()?->resourceId);

        new LinkDataMapper()->map($this->unlocalized, $this->localized, ['link' => null]);
        self::assertNull($this->localized->getLink());
    }

    /**
     * @param array<string, mixed> $link
     */
    #[DataProvider('invalidLinks')]
    public function testALinkIsRefused(array $link, string $reason): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains($reason);

        new LinkDataMapper()->map($this->unlocalized, $this->localized, ['link' => $link]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidLinks(): iterable
    {
        $both = 'A link points to a URL or to a content root, not both.';
        $neither = 'A link names a URL, or the resource key and the identifier of a content root.';

        yield 'a URL and a resource key' => [['url' => 'https://example.org/', 'resourceKey' => 'article'], $both];
        yield 'a URL and an identifier' => [['url' => 'https://example.org/', 'resourceId' => '42'], $both];
        yield 'a URL and a locale' => [['url' => 'https://example.org/', 'locale' => 'fr'], $both];
        yield 'nothing' => [[], $neither];
        yield 'a key without an identifier' => [['resourceKey' => 'article'], $neither];
        yield 'an identifier without a key' => [['resourceId' => '42'], $neither];
        yield 'a malformed key' => [['resourceKey' => 'Article', 'resourceId' => '42'], 'is not a resource key'];
        yield 'a relative URL' => [['url' => '/news'], 'is not an http or https address'];
    }

    public function testALinkWithAMalformedLocaleIsRefused(): void
    {
        $this->expectException(InvalidDimension::class);

        new LinkDataMapper()->map($this->unlocalized, $this->localized, ['link' => ['resourceKey' => 'article', 'resourceId' => '42', 'locale' => 'fr-CA']]);
    }

    public function testTheSlugIsWrittenOnTheLocalizedRow(): void
    {
        new RoutableDataMapper()->map($this->unlocalized, $this->localized, ['slug' => ' my-first-article ']);

        self::assertSame('my-first-article', $this->localized->getSlug()?->value);
        self::assertNull($this->unlocalized->getSlug());
    }

    public function testAnAbsentSlugChangesNothingAndABlankOneClears(): void
    {
        $this->localized->setSlug(Slug::fromString('kept'));

        new RoutableDataMapper()->map($this->unlocalized, $this->localized, []);
        self::assertSame('kept', $this->localized->getSlug()?->value);

        new RoutableDataMapper()->map($this->unlocalized, $this->localized, ['slug' => '']);
        self::assertNull($this->localized->getSlug());
    }

    public function testAMalformedSlugIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('is not a slug');

        new RoutableDataMapper()->map($this->unlocalized, $this->localized, ['slug' => 'My Article']);
    }

    /**
     * Compiled, then purified, then stored: the HTML on the row has been through the purifier once.
     */
    public function testTheBodyIsCompiledPurifiedAndStoredOnTheLocalizedRow(): void
    {
        $this->bodyMapper()->map($this->unlocalized, $this->localized, ['bodySource' => "    code\n", 'bodyFormat' => 'markdown']);

        $body = $this->localized->getBody();
        self::assertInstanceOf(Body::class, $body);
        self::assertSame("    code\n", $body->source);
        self::assertSame('markdown', $body->format);
        self::assertSame("<purified><markdown>    code\n</markdown></purified>", $body->html);
        self::assertNull($this->unlocalized->getBody());
    }

    public function testAnHtmlBodyIsCompiledAsHtml(): void
    {
        $this->bodyMapper()->map($this->unlocalized, $this->localized, ['bodySource' => '<p>Bonjour</p>', 'bodyFormat' => ' html ']);

        self::assertSame('html', $this->localized->getBody()?->format);
        self::assertSame('<purified><html><p>Bonjour</p></html></purified>', $this->localized->getBody()->html);
    }

    /**
     * Every format of `MarkupFormat` is a body format, as the documentation lists them.
     */
    public function testBBCodeAndTextBodiesAreCompiledInTheirFormat(): void
    {
        $this->bodyMapper()->map($this->unlocalized, $this->localized, ['bodySource' => '[b]Bonjour[/b]', 'bodyFormat' => 'bbcode']);
        self::assertSame('bbcode', $this->localized->getBody()?->format);

        $this->bodyMapper()->map($this->unlocalized, $this->localized, ['bodySource' => 'Bonjour', 'bodyFormat' => 'text']);
        self::assertSame('text', $this->localized->getBody()?->format);
    }

    public function testABodyWithoutAFormatIsMarkdown(): void
    {
        $this->bodyMapper()->map($this->unlocalized, $this->localized, ['bodySource' => '*Bonjour*']);

        self::assertSame('markdown', $this->localized->getBody()?->format);
    }

    public function testAnAbsentBodyChangesNothingAndABlankOneClears(): void
    {
        $this->localized->setBody(Body::of('kept', MarkupFormat::Markdown, '<p>kept</p>'));

        $this->bodyMapper()->map($this->unlocalized, $this->localized, ['bodyFormat' => 'html']);
        self::assertSame('kept', $this->localized->getBody()?->source);

        $this->bodyMapper()->map($this->unlocalized, $this->localized, ['bodySource' => "  \n"]);
        self::assertNull($this->localized->getBody());
    }

    public function testAnUnknownFormatIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('"textile" is not a body format; use one of: markdown, html, bbcode, mediawiki, dokuwiki, creole, text, ujo.');

        $this->bodyMapper()->map($this->unlocalized, $this->localized, ['bodySource' => '*Bonjour*', 'bodyFormat' => 'textile']);
    }

    /**
     * The trusted role sees the raw HTML of its draft as its publication will show it: compiled for `Level::All`, the
     * raw HTML a `raw` block kept as the `raw` profile allows; anyone else sees it as text.
     */
    public function testTheTrustedRoleSeesTheRawHtmlOfItsDraftRendered(): void
    {
        $source = "<div><p>Hi</p><script>x</script></div>\n";

        new BodyDataMapper(new DefaultCompiler(), new MarkingHtmlPurifier(), $this->rawHtml(true))
            ->map($this->unlocalized, $this->localized, ['bodySource' => $source]);
        self::assertSame('<purified><div class="ujo-raw"><div><p>Hi</p></div></div></purified>', $this->localized->getBody()?->html);

        new BodyDataMapper(new DefaultCompiler(), new MarkingHtmlPurifier(), $this->rawHtml(false))
            ->map($this->unlocalized, $this->localized, ['bodySource' => $source]);
        self::assertStringContainsString('&lt;div&gt;&lt;p&gt;Hi&lt;/p&gt;', (string) $this->localized->getBody()?->html);

        new BodyDataMapper(new DefaultCompiler(), new MarkingHtmlPurifier())
            ->map($this->unlocalized, $this->localized, ['bodySource' => $source]);
        self::assertStringContainsString('&lt;div&gt;&lt;p&gt;Hi&lt;/p&gt;', (string) $this->localized->getBody()?->html);
    }

    /**
     * A compiler that does not know levels compiles every draft as `compile()` does, the trusted role's included.
     */
    public function testACompilerWithoutLevelsCompilesTheTrustedRoleAsAnyone(): void
    {
        new BodyDataMapper(new EchoMarkupCompiler(), new MarkingHtmlPurifier(), $this->rawHtml(true))
            ->map($this->unlocalized, $this->localized, ['bodySource' => '<b>x</b>']);

        self::assertSame('<purified><markdown><b>x</b></markdown></purified>', $this->localized->getBody()?->html);
    }

    /**
     * A draft in UJO is checked when it is written, so its publication never meets a document it cannot read.
     */
    public function testAUjoBodyThatIsNotAValidDocumentIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The ujo source cannot be read: The block type "nope" at blocks[0] is not registered.');

        new BodyDataMapper(new DefaultCompiler(), new MarkingHtmlPurifier())
            ->map($this->unlocalized, $this->localized, ['bodySource' => '{"blocks":[{"id":"a","type":"nope","data":{}}]}', 'bodyFormat' => 'ujo']);
    }

    #[DataProvider('mappers')]
    public function testRowsWithoutTheCapabilityAreLeftAlone(DataMapper $mapper): void
    {
        $bare = new BareDimensionContent(new ExampleRoot(), DimensionKey::unlocalized(Stage::Draft, Version::first()));

        $mapper->map($bare, $bare, [
            'seoTitle' => 'x',
            'excerptTitle' => 'x',
            'excerptImage' => '/x.png',
            'link' => ['url' => 'https://example.org/'],
            'slug' => 'x',
            'bodySource' => 'x',
        ]);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return iterable<string, array{DataMapper}>
     */
    public static function mappers(): iterable
    {
        yield 'SEO' => [new SeoDataMapper()];
        yield 'excerpt' => [new ExcerptDataMapper()];
        yield 'link' => [new LinkDataMapper()];
        yield 'routable' => [new RoutableDataMapper()];
        yield 'body' => [new BodyDataMapper(new EchoMarkupCompiler(), new MarkingHtmlPurifier())];
    }

    private function rawHtml(bool $granted): RawHtmlPermission
    {
        return new readonly class($granted) implements RawHtmlPermission {
            public function __construct(
                private bool $granted,
            ) {
            }

            #[Override]
            public function isGranted(): bool
            {
                return $this->granted;
            }
        };
    }

    private function bodyMapper(): BodyDataMapper
    {
        return new BodyDataMapper(new EchoMarkupCompiler(), new MarkingHtmlPurifier());
    }
}
