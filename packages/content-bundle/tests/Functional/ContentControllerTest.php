<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Http\ContentController;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\ContentBundle\Infrastructure\Twig\ContentExtension;
use Phil\ContentBundle\Infrastructure\Twig\ContentRuntime;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Requests through the kernel: the controller, the route table, the template chosen by its key, and the
 * Twig functions the template calls.
 */
#[CoversClass(ContentController::class)]
#[CoversClass(ContentUrlGenerator::class)]
#[CoversClass(ContentExtension::class)]
#[CoversClass(ContentRuntime::class)]
final class ContentControllerTest extends FunctionalTestCase
{
    public function testAPublishedNewsIsRenderedThroughTheTemplateOfItsKey(): void
    {
        $news = $this->publishedNews();

        $response = $this->request('/fr/news/ouverture');
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<title>Le site ouvre &amp; accueille</title>', $html);
        self::assertStringContainsString('<meta name="description" content="Une &quot;grande&quot; ouverture">', $html);
        self::assertStringContainsString('<meta name="keywords" content="larp, ouverture">', $html);
        self::assertStringContainsString('<meta name="robots" content="nofollow">', $html);
        self::assertStringContainsString('<link rel="canonical" href="https://example.org/fr/ouverture">', $html);
        self::assertStringContainsString('<script type="application/ld+json">{"@context":"https://schema.org","@type":"NewsArticle","name":"Le site ouvre & accueille","headline":"Le site ouvre & accueille","description":"Une \\"grande\\" ouverture","datePublished":', $html);
        self::assertStringContainsString('<b>ouvre</b>', $html);
        self::assertStringNotContainsString('<script>', $html, 'The body is purified.');
        self::assertStringContainsString('<p class="locale">fr|fr|stayed</p>', $html);
        self::assertStringContainsString('<a class="self" href="/fr/news/ouverture">', $html);
        self::assertStringContainsString('<a class="link" href="https://example.org/plus">', $html);
        self::assertSame('news', $news->resourceKey);
    }

    public function testARegionalLocaleIsServedAlongItsChain(): void
    {
        $this->publishedNews();

        $html = (string) $this->request('/fr_CA/news/ouverture')->getContent();

        self::assertStringContainsString('<p class="locale">fr_CA|fr|stayed</p>', $html);
        self::assertStringContainsString('<a class="self" href="">', $html, 'The root has no route of its own in fr_CA.');
    }

    public function testAnOldSlugRedirectsPermanently(): void
    {
        $news = $this->publishedNews();
        $this->contents()->edit($news, 'fr', ['slug' => 'grande-ouverture']);
        $this->contents()->publish($news, 'fr');

        $response = $this->request('/fr/news/ouverture');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/fr/news/grande-ouverture', $response->headers->get('Location'));
        self::assertSame(200, $this->request('/fr/news/grande-ouverture')->getStatusCode());
    }

    /**
     * The root publishes `fr_CA` under a slug of its own: its `fr` slug requested in `fr_CA` would serve the
     * `fr_CA` content at a second address, so it redirects.
     */
    public function testASlugOfAnotherLocaleOfTheChainRedirectsToTheSlugOfTheLocaleServed(): void
    {
        $news = $this->publishedNews();
        $this->contents()->edit($news, 'fr_CA', ['slug' => 'ouverture-qc', 'excerptTitle' => 'Le site ouvre au Québec']);
        $this->contents()->publish($news, 'fr_CA');

        $response = $this->request('/fr_CA/news/ouverture');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/fr_CA/news/ouverture-qc', $response->headers->get('Location'));

        $html = (string) $this->request('/fr_CA/news/ouverture-qc')->getContent();
        self::assertStringContainsString('<p class="locale">fr_CA|fr_CA|stayed</p>', $html);
        self::assertStringContainsString('<h1>Le site ouvre au Québec</h1>', $html);
    }

    /**
     * A locale published without a slug has no address of its own: the slug that matched serves its own
     * locale, not the one before it in the chain.
     */
    public function testALocaleWithoutARouteOfItsOwnIsNotServedAtTheAddressOfAnother(): void
    {
        $news = $this->publishedNews();
        $this->contents()->edit($news, 'fr_CA', ['excerptTitle' => 'Le site ouvre au Québec']);
        $this->contents()->publish($news, 'fr_CA');

        $html = (string) $this->request('/fr_CA/news/ouverture')->getContent();

        self::assertStringContainsString('<p class="locale">fr_CA|fr|stayed</p>', $html);
        self::assertStringContainsString('<h1>Le site ouvre &amp; accueille</h1>', $html);
    }

    public function testADeletedRootIsNotServedAndItsSlugIsFree(): void
    {
        $news = $this->publishedNews();

        $this->contents()->delete($news);

        self::assertSame(404, $this->request('/fr/news/ouverture')->getStatusCode());

        $next = $this->contents()->create('news', 'fr', ['templateKey' => 'news', 'slug' => 'ouverture', 'excerptTitle' => 'Réouverture']);
        $this->contents()->publish($next, 'fr');

        self::assertStringContainsString('<h1>Réouverture</h1>', (string) $this->request('/fr/news/ouverture')->getContent());
    }

    public function testWhatIsNotServedIsNotFound(): void
    {
        $news = $this->publishedNews();

        self::assertSame(404, $this->request('/fr/news/inconnue')->getStatusCode(), 'an unknown slug');
        self::assertSame(404, $this->request('/en/news/ouverture')->getStatusCode(), 'a locale whose chain holds no content');
        self::assertSame(404, $this->request('/de/news/ouverture')->getStatusCode(), 'a locale that is not served');
        self::assertSame(404, $this->request('/fr-CA/news/ouverture')->getStatusCode(), 'a malformed locale');
        self::assertSame(404, $this->request('/fr/ouverture')->getStatusCode(), 'a slug of another kind');

        $this->contents()->unpublish($news, 'fr');

        self::assertSame(404, $this->request('/fr/news/ouverture')->getStatusCode(), 'an unpublished locale');
    }

    public function testADraftIsNeverServed(): void
    {
        $news = $this->publishedNews();
        $this->contents()->edit($news, 'fr', ['excerptTitle' => 'Brouillon']);

        $html = (string) $this->request('/fr/news/ouverture')->getContent();

        self::assertStringContainsString('<h1>Le site ouvre &amp; accueille</h1>', $html);
        self::assertStringNotContainsString('Brouillon', $html);
    }

    public function testARowWithoutTemplateKeyIsRenderedThroughTheDefaultTemplate(): void
    {
        $page = $this->contents()->create('static_page', 'fr', ['slug' => 'a-propos', 'bodySource' => 'À propos']);
        $this->contents()->publish($page, 'fr');

        $response = $this->request('/fr/a-propos');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame("<main data-reference=\"static_page:1\"><p>À propos</p></main>\n", $response->getContent());
    }

    public function testARouteOfItsOwnServesItsSlugAndRedirectsThroughTheGeneratingRoute(): void
    {
        $page = $this->contents()->create('static_page', 'fr', ['slug' => 'a-propos', 'bodySource' => 'À propos']);
        $this->contents()->publish($page, 'fr');

        $response = $this->request('/fr/about');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<p>À propos</p>', (string) $response->getContent());
        self::assertSame('/fr/a-propos', $this->service(ContentRuntime::class)->url($page, 'fr'), 'URLs are generated on the first route.');

        $this->contents()->edit($page, 'fr', ['slug' => 'qui-sommes-nous']);
        $this->contents()->publish($page, 'fr');
        $redirect = $this->request('/fr/about');

        self::assertSame(301, $redirect->getStatusCode());
        self::assertSame('/fr/qui-sommes-nous', $redirect->headers->get('Location'));
    }

    public function testTheRuntimeReadsLiveContentAndUrls(): void
    {
        $news = $this->publishedNews();
        $runtime = $this->service(ContentRuntime::class);
        $this->entityManager()->clear();

        self::assertSame('Le site ouvre & accueille', $runtime->content($news, 'fr')['excerptTitle'] ?? null);
        self::assertSame('Le site ouvre & accueille', $runtime->content($news->toString(), 'fr_CA')['excerptTitle'] ?? null);
        self::assertNull($runtime->content($news, 'en'), 'Nothing published in the chain.');
        self::assertNull($runtime->content(ContentReference::of('news', '0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b'), 'fr'));
        self::assertSame('http://localhost/fr/news/ouverture', $runtime->url($news, 'fr', true));
        self::assertNull($runtime->url($news, 'en'), 'No route in that locale.');
        self::assertSame('/fr/news/ouverture', $runtime->linkUrl(['resourceKey' => 'news', 'resourceId' => $news->resourceId, 'locale' => 'fr', 'url' => null]));
        self::assertNull($runtime->linkUrl(null));
        self::assertNull($runtime->linkUrl(['resourceKey' => null, 'resourceId' => null, 'locale' => null, 'url' => null]));
        self::assertNull($runtime->content($news, 'de'), 'A locale that is not served.');
        self::assertNull($runtime->content($news, 'fr-CA'), 'A malformed locale.');
        self::assertNull($runtime->content('not a reference', 'fr'));
        self::assertNull($runtime->content('news:abc', 'fr'), 'An identifier that cannot be a news.');
        self::assertNull($runtime->url($news, 'de'));
        self::assertNull($runtime->url('not a reference', 'fr'));
        self::assertNull($runtime->linkUrl(['resourceKey' => 'news', 'resourceId' => 'not an id!', 'locale' => null, 'url' => null], 'fr'));
        self::assertNull($runtime->linkUrl(['resourceKey' => 'news', 'resourceId' => $news->resourceId, 'locale' => 'en_us', 'url' => null]));
        self::assertSame('', $runtime->seo([]));
        self::assertSame('<meta name="robots" content="noindex, nofollow">', $runtime->seo(['seoNoIndex' => true, 'seoNoFollow' => true]));
        self::assertSame('<meta name="description" content="Extrait">', $runtime->seo(['seoDescription' => null, 'excerptDescription' => 'Extrait']));
    }

    private function publishedNews(): ContentReference
    {
        $news = $this->contents()->create('news', 'fr', [
            'templateKey' => 'news',
            'slug' => 'ouverture',
            'excerptTitle' => 'Le site ouvre & accueille',
            'seoDescription' => 'Une "grande" ouverture',
            'seoKeywords' => ['larp', 'ouverture'],
            'seoNoFollow' => true,
            'seoCanonicalUrl' => 'https://example.org/fr/ouverture',
            'link' => ['url' => 'https://example.org/plus'],
            'bodySource' => "Le site **ouvre**.\n\n<script>alert(1)</script>",
        ]);
        $this->contents()->publish($news, 'fr');

        return $news;
    }
}
