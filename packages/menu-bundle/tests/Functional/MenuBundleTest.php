<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Tests\Functional;

use Override;
use Phil\MenuBundle\PhilMenuBundle;
use Phil\MenuBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Menus in a real kernel: the builder and the decorator found by autoconfiguration, the current page read from the
 * route, the templates rendered in French.
 */
#[CoversClass(PhilMenuBundle::class)]
final class MenuBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    public function testTheMenuIsRenderedWithItsCurrentPage(): void
    {
        $html = $this->page('/games/chess');

        self::assertStringContainsString('<li class="site"><a href="/">Accueil</a></li>', $html);
        self::assertStringContainsString('<li class="site" data-active-trail><a href="/games">Jeux</a>', $html);
        self::assertStringContainsString('<a href="/games/chess" aria-current="page">Échecs</a>', $html);
        self::assertStringContainsString('<a href="https://example.com/">Ailleurs</a>', $html);
    }

    public function testTheBreadcrumbAndTheCardsAreRendered(): void
    {
        $html = $this->page('/games/chess');

        self::assertStringContainsString('<nav aria-label="Fil d’Ariane" class="breadcrumb">', $html);
        self::assertStringContainsString('<li><a href="/games">Jeux</a></li>', $html);
        self::assertStringContainsString('<li><span aria-current="page">Échecs</span></li>', $html);
        self::assertStringContainsString('<i class="icon-dice" aria-hidden="true"></i> <h3>Jeux</h3>', $html);
        self::assertStringContainsString('<span class="menu-card__count">120 en tout</span>', $html);
        self::assertStringContainsString('<span class="menu-card__new">4 nouveaux</span>', $html);
        self::assertStringContainsString('class="menu-card site"', $html);
    }

    public function testTheBreadcrumbSchemaIsTranslatedWithAbsoluteUrls(): void
    {
        $html = $this->page('/games/chess');

        self::assertStringContainsString('{"@type":"ListItem","position":1,"name":"Jeux","item":"http://localhost/games"}', $html);
        self::assertStringContainsString('{"@type":"ListItem","position":2,"name":"Échecs","item":"http://localhost/games/chess"}', $html);
    }

    public function testAPageOutsideTheMenuHasNoBreadcrumb(): void
    {
        $html = $this->page('/about');

        self::assertStringNotContainsString('Fil d’Ariane', $html);
        self::assertStringNotContainsString('aria-current', $html);
    }

    private function page(string $path): string
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        return (string) $kernel->handle(Request::create($path))->getContent();
    }
}
