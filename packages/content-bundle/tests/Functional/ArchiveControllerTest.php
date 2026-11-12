<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Phil\ContentBundle\Application\Publication\ContentArchive;
use Phil\ContentBundle\Infrastructure\Http\ArchiveController;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

/**
 * The dated archive of a kind declared under `phil_content.archives`, served by `ArchiveController` and listed by the
 * Twig function `content_archive`.
 */
#[CoversClass(ArchiveController::class)]
#[CoversClass(ContentArchive::class)]
final class ArchiveControllerTest extends FunctionalTestCase
{
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testEachPublicationStaysReadableAtItsVersion(): void
    {
        Clock::set(new MockClock('2026-10-01 12:00:00 UTC'));
        $news = $this->contents()->create('news', 'fr', ['templateKey' => 'news', 'slug' => 'maintenant', 'excerptTitle' => 'Octobre']);
        $this->contents()->publish($news, 'fr');
        Clock::set(new MockClock('2026-11-01 12:00:00 UTC'));
        $this->contents()->edit($news, 'fr', ['excerptTitle' => 'Novembre']);
        $this->contents()->publish($news, 'fr');

        $october = $this->request('/fr/news/maintenant/v1');
        $current = $this->request('/fr/news/maintenant');

        self::assertSame(200, $october->getStatusCode());
        self::assertStringContainsString('<h1>Octobre</h1>', (string) $october->getContent());
        self::assertStringContainsString('<ol class="archive"><li>2@2026-11-01 12:00</li><li>1@2026-10-01 12:00</li></ol><p class="archived">1</p>', (string) $october->getContent());
        self::assertStringContainsString('<h1>Novembre</h1>', (string) $current->getContent());
        self::assertStringContainsString('<ol class="history"><li>2</li><li>1</li></ol>', (string) $current->getContent());
    }

    public function testWhatWasNeverPublishedIsNotFound(): void
    {
        $news = $this->contents()->create('news', 'fr', ['templateKey' => 'news', 'slug' => 'maintenant', 'excerptTitle' => 'Octobre']);

        self::assertSame(404, $this->request('/fr/news/maintenant/v1')->getStatusCode(), 'A draft is not in the archive.');

        $this->contents()->publish($news, 'fr');
        $this->contents()->edit($news, 'fr', ['excerptTitle' => 'Brouillon']);

        self::assertSame(200, $this->request('/fr/news/maintenant/v1')->getStatusCode());
        self::assertSame(404, $this->request('/fr/news/maintenant/v2')->getStatusCode(), 'The pending draft is not in the archive.');
        self::assertSame(404, $this->request('/fr/news/maintenant/v0')->getStatusCode());
        self::assertSame(404, $this->request('/fr/news/ailleurs/v1')->getStatusCode());
        self::assertSame(404, $this->request('/de/news/maintenant/v1')->getStatusCode(), 'A locale the site does not serve.');
        self::assertSame(404, $this->request('/en/news/maintenant/v1')->getStatusCode(), 'An archive does not fall back.');
    }
}
