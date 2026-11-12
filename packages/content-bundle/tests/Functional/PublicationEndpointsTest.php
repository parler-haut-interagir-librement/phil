<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Phil\ContentBundle\Infrastructure\Http\PreviewLinks;
use Phil\ContentBundle\PhilContentBundle;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A feed and a preview link through the kernel: the routes the bundle ships, its controllers wired.
 */
#[CoversClass(PhilContentBundle::class)]
final class PublicationEndpointsTest extends FunctionalTestCase
{
    public function testTheFeedAndThePreviewAnswer(): void
    {
        $news = $this->contents()->create('news', 'fr', ['templateKey' => 'news', 'slug' => 'ouverture', 'excerptTitle' => 'Le site ouvre']);
        $this->contents()->publish($news, 'fr');
        $draft = $this->contents()->create('news', 'fr', ['templateKey' => 'news', 'slug' => 'bientot', 'excerptTitle' => 'Bientôt']);

        self::assertSame(404, $this->request('/sitemap.xml')->getStatusCode());

        $feed = $this->request('/fr/feeds/news.atom');
        self::assertSame('application/atom+xml; charset=UTF-8', $feed->headers->get('Content-Type'));
        self::assertStringContainsString('<title>Nouvelles</title>', (string) $feed->getContent());
        self::assertStringContainsString('<title>Le site ouvre</title>', (string) $feed->getContent());

        $link = $this->service(PreviewLinks::class)->url($draft, Locale::fromString('fr'));
        $preview = $this->request(substr($link, \strlen('http://localhost')));
        self::assertSame(200, $preview->getStatusCode(), (string) $preview->getContent());
        self::assertStringContainsString('Bientôt', (string) $preview->getContent());
        self::assertSame('noindex, nofollow', $preview->headers->get('X-Robots-Tag'));
        self::assertSame(404, $this->request('/preview/'.$draft->toString().'/fr')->getStatusCode());
    }
}
