<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Twig;

use Override;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Routing\Route as ContentRoute;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\ContentBundle\Infrastructure\Http\ResourceRoutes;
use Phil\ContentBundle\Infrastructure\Twig\ContentRuntime;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(ContentRuntime::class)]
final class ContentRuntimeTest extends TestCase
{
    private InMemoryContents $contents;

    private ContentRuntime $runtime;

    #[Override]
    protected function setUp(): void
    {
        $this->contents = new InMemoryContents();
        $this->runtime = $this->runtime();
    }

    /**
     * A route left in a locale the site no longer serves gives no URL.
     */
    public function testALocaleNoLongerServedHasNoUrl(): void
    {
        $reference = ContentReference::of('example', '1');
        $this->contents->routes->add(ContentRoute::current($reference, Locale::fromString('fr'), Slug::fromString('page')));
        $this->contents->routes->add(ContentRoute::current($reference, Locale::fromString('de'), Slug::fromString('seite')));

        self::assertSame('/fr/example/page', $this->runtime->url($reference, 'fr'));
        self::assertNull($this->runtime->url($reference, 'de'));
    }

    /**
     * Values are escaped for HTML5 — an apostrophe as `&apos;` —, and a byte that is not UTF-8 becomes U+FFFD.
     */
    public function testTheSeoTagsEscapeForHtml5(): void
    {
        self::assertSame('<meta name="description" content="l&apos;été &amp; &quot;plus&quot;">', $this->runtime->seo(['seoDescription' => 'l\'été & "plus"']));
        self::assertSame("<meta name=\"description\" content=\"a\u{FFFD}b\">", $this->runtime->seo(['seoDescription' => "a\xFFb"]));
    }

    /**
     * A content is named by its reference, by its root or by the string form of its reference.
     */
    public function testAContentIsNamedByReferenceByRootOrByString(): void
    {
        $root = new ExampleRoot();
        $this->contents->roots->add($root);
        $this->contents->roots->flush();
        $this->contents->routes->add(ContentRoute::current(ContentReference::of('example', '1'), Locale::fromString('fr'), Slug::fromString('page')));

        self::assertSame('/fr/example/page', $this->runtime->url(ContentReference::of('example', '1'), 'fr'));
        self::assertSame('/fr/example/page', $this->runtime->url($root, 'fr'));
        self::assertSame('/fr/example/page', $this->runtime->url('example:1', 'fr'));
    }

    /**
     * The locale given wins over the request's, which wins over the first locale declared; without any, `en`.
     */
    public function testTheLocaleIsTheOneGivenThenTheRequestsThenTheFirstDeclared(): void
    {
        $reference = ContentReference::of('example', '1');
        $this->contents->routes->add(ContentRoute::current($reference, Locale::fromString('fr'), Slug::fromString('page')));
        $request = new Request();
        $request->setLocale('en');

        $requests = new RequestStack([$request]);
        $withRequest = $this->runtime($requests);

        self::assertSame('/fr/example/page', $withRequest->url($reference, 'fr'), "The locale given wins over the request's.");
        self::assertSame('en', $withRequest->schema([])['inLanguage'], "The request's locale wins over the first declared.");
        self::assertSame('fr-CA', $this->runtime->schema([])['inLanguage'], 'Without a request, the first locale declared.');
        self::assertSame('en', $this->runtime(locales: DeclaredLocales::fromArray([]))->schema([])['inLanguage']);
    }

    public function testTheSeoTagsDescribeTheContent(): void
    {
        self::assertSame(implode("\n", [
            '<meta name="description" content="seo">',
            '<meta name="keywords" content="a, b">',
            '<meta name="robots" content="noindex, nofollow">',
            '<link rel="canonical" href="https://example.com/a?b=1&amp;c=2">',
        ]), $this->runtime->seo([
            'seoDescription' => 'seo',
            'excerptDescription' => 'excerpt',
            'seoKeywords' => ['a', 3, 'b'],
            'seoNoIndex' => true,
            'seoNoFollow' => true,
            'seoCanonicalUrl' => 'https://example.com/a?b=1&c=2',
        ]));
        self::assertSame('<meta name="description" content="excerpt">', $this->runtime->seo(['excerptDescription' => 'excerpt', 'seoKeywords' => []]));
        self::assertSame('<meta name="robots" content="noindex">', $this->runtime->seo(['seoNoIndex' => true, 'seoNoFollow' => 'yes', 'seoKeywords' => 'a']));
        self::assertSame('<meta name="robots" content="nofollow">', $this->runtime->seo(['seoNoFollow' => true]));
        self::assertSame('', $this->runtime->seo([]));
    }

    public function testAPublishedContentIsReadByReferenceByRootOrByString(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);
        $this->contents->manager->publish($reference, 'fr');
        $root = $this->contents->roots->find($reference);
        self::assertNotNull($root);

        foreach ([$reference, $root, 'example:1'] as $named) {
            $content = $this->runtime->content($named, 'fr');
            self::assertNotNull($content);
            self::assertSame('Ouverture', $content['excerptTitle'] ?? null);
        }

        self::assertNull($this->runtime->content('example:9', 'fr'), 'A content that does not exist has none.');
    }

    public function testWithoutSingleKindsNoKindIsSingle(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('"example" is not a single kind; declare it under "phil_content.singles".');

        $this->runtime->single('example');
    }

    private function runtime(RequestStack $requests = new RequestStack(), ?DeclaredLocales $locales = null): ContentRuntime
    {
        $routes = new RouteCollection();
        $routes->add('example_show', new Route('/{_locale}/example/{slug}'));

        $urls = new ContentUrlGenerator($this->contents->routes, new ResourceRoutes(['example' => 'example_show']), new UrlGenerator($routes, new RequestContext()));

        return new ContentRuntime($this->contents->roots, $this->contents->reader, $this->contents->normalizer, $locales ?? $this->contents->locales, $urls, $requests);
    }
}
