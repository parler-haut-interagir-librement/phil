<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Tests\Unit\Application;

use Closure;
use Override;
use Phil\MenuBundle\Application\MenuBuilder;
use Phil\MenuBundle\Application\MenuContext;
use Phil\MenuBundle\Application\MenuDecorator;
use Phil\MenuBundle\Application\Menus;
use Phil\MenuBundle\Domain\InvalidMenu;
use Phil\MenuBundle\Domain\Menu;
use Phil\MenuBundle\Domain\MenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Menus::class)]
#[CoversClass(MenuContext::class)]
final class MenusTest extends TestCase
{
    private function builder(string $name = 'main'): MenuBuilder
    {
        return new readonly class($name) implements MenuBuilder {
            public function __construct(
                private string $name,
            ) {
            }

            #[Override]
            public function name(): string
            {
                return $this->name;
            }

            #[Override]
            public function build(MenuContext $context): Menu
            {
                return Menu::of($this->name, [
                    MenuItem::of('home', 'Home')->withUrl('/'),
                    MenuItem::of('games', 'Games')->withRoute('games')->withChildren(
                        MenuItem::of('chess', 'Chess')->withRoute('game', ['slug' => 'chess', 'page' => 1]),
                        MenuItem::of('about', 'About')->withUrl('/games/about?tab=1'),
                        MenuItem::of('outside', 'Outside')->withUrl('https://example.com/games/about'),
                        MenuItem::of('heading', 'Heading'),
                    ),
                ]);
            }
        };
    }

    private function decorator(string $menu, string $class): MenuDecorator
    {
        return new readonly class($menu, $class) implements MenuDecorator {
            public function __construct(
                private string $menu,
                private string $class,
            ) {
            }

            #[Override]
            public function supports(string $menu): bool
            {
                return $menu === $this->menu;
            }

            #[Override]
            public function decorate(MenuItem $item, MenuContext $context): MenuItem
            {
                return $item->withClass($this->class.'-'.($context->locale ?? 'none'));
            }
        };
    }

    /**
     * @return iterable<string, array{MenuContext, ?string, list<string>}>
     */
    public static function contexts(): iterable
    {
        yield 'nowhere' => [new MenuContext(), null, []];
        yield 'a route' => [new MenuContext('games'), 'games', []];
        yield 'a route with its parameters' => [new MenuContext('game', ['slug' => 'chess', 'page' => '1', 'extra' => 'x']), 'chess', ['games']];
        yield 'a route with other parameters' => [new MenuContext('game', ['slug' => 'go', 'page' => '1']), null, []];
        yield 'a route with a number for a parameter' => [new MenuContext('game', ['slug' => 'chess', 'page' => 1]), 'chess', ['games']];
        yield 'a route with a parameter that is no text' => [new MenuContext('game', ['slug' => ['chess'], 'page' => '1']), null, []];
        yield 'a route missing a parameter' => [new MenuContext('game', ['slug' => 'chess']), null, []];
        yield 'a path' => [new MenuContext(path: '/'), 'home', []];
        yield 'the path of an address' => [new MenuContext(path: '/games/about'), 'about', ['games']];
        yield 'another path' => [new MenuContext(path: '/games'), null, []];
    }

    /**
     * @param list<string> $ancestors
     */
    #[DataProvider('contexts')]
    public function testTheCurrentItemAndItsAncestorsAreMarked(MenuContext $context, ?string $current, array $ancestors): void
    {
        $menu = new Menus([$this->builder()])->get('main', $context);
        $flagged = static function (Menu $menu, Closure $flag): array {
            $keys = [];
            $menu->map(static function (MenuItem $item) use (&$keys, $flag): MenuItem {
                if (true === $flag($item)) {
                    $keys[] = $item->key;
                }

                return $item;
            });

            return $keys;
        };

        self::assertSame(null === $current ? [] : [$current], $flagged($menu, static fn (MenuItem $item): bool => $item->current));
        self::assertSame($ancestors, $flagged($menu, static fn (MenuItem $item): bool => $item->currentAncestor));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'the same path' => ['/games/about', '/games/about', true];
        yield 'a query and a fragment' => ['/games/about?tab=1#top', '/games/about', true];
        // The browser resolves the segments `.` and `..`, escaped or not, before it asks for the page.
        yield 'dot segments' => ['/games/rules/../about', '/games/about', true];
        yield 'escaped dot segments' => ['/games/rules/%2e%2E/./about', '/games/about', true];
        yield 'a letter written as it is' => ['/jeux/thé', '/jeux/th%C3%A9', true];
        yield 'an escape of the visitor' => ['/games/about', '/games/./about', true];
        yield 'another path' => ['/games/about', '/games/rules', false];
        yield 'a path relative to the page' => ['games/about', '/games/about', false];
        yield 'another host' => ['//example.com/games/about', '/games/about', false];
        yield 'an address of the web' => ['https://example.com/games/about', '/games/about', false];
        yield 'a query alone' => ['?tab=1', '/games/about', false];
        yield 'not a path' => ['/games/about', 'games about', false];
        yield 'a visitor without a slash' => ['/', 'games', false];
        yield 'a visitor on another host' => ['/', '//example.com/', false];
    }

    #[DataProvider('paths')]
    public function testAnAddressIsCurrentWhenTheBrowserReadsTheVisitorsPath(string $url, string $path, bool $current): void
    {
        $builder = new readonly class($url) implements MenuBuilder {
            public function __construct(
                private string $url,
            ) {
            }

            #[Override]
            public function name(): string
            {
                return 'main';
            }

            #[Override]
            public function build(MenuContext $context): Menu
            {
                return Menu::of('main', [MenuItem::of('page', 'Page')->withUrl($this->url)]);
            }
        };

        self::assertSame($current, new Menus([$builder])->get('main', new MenuContext(path: $path))->find('page')?->current);
    }

    public function testDecoratorsOfTheMenuRunInOrder(): void
    {
        $menus = new Menus([$this->builder(), $this->builder('footer')], [$this->decorator('main', 'first'), $this->decorator('footer', 'other'), $this->decorator('main', 'second')]);
        $menu = $menus->get('main', new MenuContext(locale: 'fr'));

        self::assertSame('first-fr second-fr', $menu->find('chess')?->attributes['class']);
        self::assertSame('first-fr second-fr', $menu->find('games')?->attributes['class']);
        self::assertSame('other-none', $menus->get('footer')->find('home')?->attributes['class']);
        self::assertSame(['footer', 'main'], $menus->names());
    }

    public function testAnUnknownMenuIsRefused(): void
    {
        $this->expectException(InvalidMenu::class);
        $this->expectExceptionMessageIsOrContains('No builder makes the menu "side"; the menus are: footer, main.');

        new Menus([$this->builder('main'), $this->builder('footer')])->get('side');
    }

    public function testTwoBuildersOfAMenuAreRefused(): void
    {
        $this->expectException(InvalidMenu::class);
        $this->expectExceptionMessageIsOrContains('Two builders make the menu "main".');

        new Menus([$this->builder(), $this->builder()]);
    }

    public function testWithoutBuildersThereIsNoMenu(): void
    {
        self::assertSame([], new Menus()->names());
        self::assertSame(['2', 'main'], new Menus([$this->builder(), $this->builder('2')])->names());
    }
}
