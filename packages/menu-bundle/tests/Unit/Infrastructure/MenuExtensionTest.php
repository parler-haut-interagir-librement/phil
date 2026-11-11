<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Tests\Unit\Infrastructure;

use Phil\MenuBundle\Application\BreadcrumbSchema;
use Phil\MenuBundle\Application\MenuBuilder;
use Phil\MenuBundle\Application\MenuContext;
use Phil\MenuBundle\Application\Menus;
use Phil\MenuBundle\Domain\Menu;
use Phil\MenuBundle\Domain\MenuItem;
use Phil\MenuBundle\Infrastructure\Twig\MenuExtension;
use Phil\MenuBundle\Tests\Unit\Fixtures\MainMenu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(MenuExtension::class)]
#[CoversClass(BreadcrumbSchema::class)]
final class MenuExtensionTest extends TestCase
{
    public function testTheCurrentItemIsReadFromTheRequest(): void
    {
        $request = Request::create('/games/chess');
        $request->attributes->set('_route', 'game');
        $request->attributes->set('_route_params', ['slug' => 'chess']);

        $requests = new RequestStack([$request]);
        $extension = new MenuExtension(new Menus([new MainMenu()]), $requests);

        self::assertTrue($extension->menu('main')->find('chess')?->current);
        self::assertSame(['games', 'chess'], array_map(static fn (MenuItem $item): string => $item->key, $extension->breadcrumb('main')));
    }

    public function testAPathAloneIsEnough(): void
    {
        $requests = new RequestStack([Request::create('/')]);
        $request = $requests->getCurrentRequest();
        self::assertNotNull($request);
        $request->attributes->set('_route', ['not a name']);
        $request->attributes->set('_route_params', 'not parameters');

        self::assertSame([], new MenuExtension(new Menus([new MainMenu()]), $requests)->breadcrumb('main'));
    }

    public function testWithoutRequestNothingIsCurrent(): void
    {
        self::assertSame([], new MenuExtension(new Menus([new MainMenu()]), new RequestStack())->breadcrumb('main'));
    }

    public function testTheBreadcrumbIsABreadcrumbList(): void
    {
        $request = Request::create('/games/chess');
        $request->attributes->set('_route', 'game');
        $request->attributes->set('_route_params', ['slug' => 'chess']);

        $schema = new MenuExtension(new Menus([new MainMenu()]), new RequestStack([$request]))->breadcrumbSchema('main');

        self::assertSame(['https://schema.org', 'BreadcrumbList'], [$schema['@context'], $schema['@type']]);
        self::assertIsArray($schema['itemListElement']);
        self::assertSame([1, 2], array_column($schema['itemListElement'], 'position'));

        self::assertSame(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => 'https://example.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Échecs'],
        ]], BreadcrumbSchema::of([MenuItem::of('home', 'Accueil')->withUrl('https://example.com/'), MenuItem::of('chess', 'Échecs')]));
        self::assertSame([], BreadcrumbSchema::of([])['itemListElement']);
    }

    public function testTheSchemaHasTheTranslatedLabelsAndAbsoluteUrls(): void
    {
        $request = Request::create('https://shop.example.com/games/chess');
        $request->attributes->set('_route', 'game');
        $request->attributes->set('_route_params', ['slug' => 'chess']);

        $routes = new RouteCollection();
        $routes->add('games', new Route('/games'));
        $routes->add('game', new Route('/games/{slug}'));

        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['menu.games' => 'Jeux', 'menu.chess' => 'Échecs'], 'fr', 'messages');

        $extension = new MenuExtension(new Menus([new MainMenu()]), new RequestStack([$request]), $translator, new UrlGenerator($routes, new RequestContext()->fromRequest($request)));

        self::assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Jeux', 'item' => 'https://shop.example.com/games'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Échecs', 'item' => 'https://shop.example.com/games/chess'],
        ], $extension->breadcrumbSchema('main')['itemListElement']);
    }

    public function testAnAddressOnTheSiteIsMadeAbsolute(): void
    {
        $request = Request::create('https://shop.example.com/about');
        $menu = new class implements MenuBuilder {
            public function name(): string
            {
                return 'pages';
            }

            public function build(MenuContext $context): Menu
            {
                return Menu::of('pages', [MenuItem::of('top', 'Top')->withUrl('/')->withChildren(
                    MenuItem::of('cdn', 'CDN')->withUrl('//cdn.example.com/x')->withChildren(
                        MenuItem::of('anchor', 'Anchor')->withUrl('#here')->withChildren(MenuItem::of('about', 'About')->withUrl('/about')),
                    ),
                )]);
            }
        };

        self::assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Top', 'item' => 'https://shop.example.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'CDN', 'item' => 'https://cdn.example.com/x'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Anchor'],
            ['@type' => 'ListItem', 'position' => 4, 'name' => 'About', 'item' => 'https://shop.example.com/about'],
        ], new MenuExtension(new Menus([$menu]), new RequestStack([$request]))->breadcrumbSchema('pages')['itemListElement']);
    }

    public function testAnAddressOffTheSiteIsKeptAndWithoutRequestOnlyItHasAUrl(): void
    {
        $menu = new class implements MenuBuilder {
            public function name(): string
            {
                return 'links';
            }

            public function build(MenuContext $context): Menu
            {
                return Menu::of('links', [MenuItem::of('home', 'Home')->withUrl('https://example.com/')->withChildren(
                    MenuItem::of('plain', 'Plain')->withChildren(
                        MenuItem::of('search', 'Search')->withUrl('/search?q=a:b')->withChildren(MenuItem::of('cdn', 'CDN')->withUrl('//cdn.example.com/x')->markCurrent()),
                    ),
                )]);
            }
        };

        self::assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://example.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Plain'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Search', 'item' => 'https://shop.example.com/search?q=a:b'],
            ['@type' => 'ListItem', 'position' => 4, 'name' => 'CDN', 'item' => 'https://cdn.example.com/x'],
        ], new MenuExtension(new Menus([$menu]), new RequestStack([Request::create('https://shop.example.com/about')]))->breadcrumbSchema('links')['itemListElement']);
        self::assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://example.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Plain'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Search'],
            ['@type' => 'ListItem', 'position' => 4, 'name' => 'CDN'],
        ], new MenuExtension(new Menus([$menu]), new RequestStack())->breadcrumbSchema('links')['itemListElement']);
    }

    public function testWithoutTranslatorNorRouterTheLabelIsTheNameAndARouteHasNoUrl(): void
    {
        $request = Request::create('/games/chess');
        $request->attributes->set('_route', 'game');
        $request->attributes->set('_route_params', ['slug' => 'chess']);

        self::assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'menu.games'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'menu.chess'],
        ], new MenuExtension(new Menus([new MainMenu()]), new RequestStack([$request]))->breadcrumbSchema('main')['itemListElement']);
    }

    public function testAMenuIsBuiltOncePerRequest(): void
    {
        $builder = new class implements MenuBuilder {
            public int $built = 0;

            public function name(): string
            {
                return 'main';
            }

            public function build(MenuContext $context): Menu
            {
                ++$this->built;

                return new MainMenu()->build($context);
            }
        };
        $requests = new RequestStack([Request::create('/games')]);
        $extension = new MenuExtension(new Menus([$builder]), $requests);

        $extension->menu('main');
        $extension->breadcrumb('main');
        $extension->breadcrumbSchema('main');
        self::assertSame(1, $builder->built);

        $requests->push(Request::create('/games/chess'));
        $extension->menu('main');
        self::assertSame(2, $builder->built);

        $alone = new MenuExtension(new Menus([$builder]), new RequestStack());
        $alone->menu('main');
        $alone->menu('main');
        self::assertSame(4, $builder->built);
    }
}
