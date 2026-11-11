<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\MenuBundle\Domain\InvalidMenu;
use Phil\MenuBundle\Domain\Menu;
use Phil\MenuBundle\Domain\MenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MenuItem::class)]
#[CoversClass(Menu::class)]
#[CoversClass(InvalidMenu::class)]
final class MenuItemTest extends TestCase
{
    public function testAnItemIsBuiltStepByStep(): void
    {
        $at = new DateTimeImmutable('2026-11-03 12:00');
        $item = MenuItem::of('games', 'Games')
            ->withUrl('https://example.com/games')
            ->withIcon('icon-dice')
            ->withDescription('Every game.')
            ->withCount(120, 4)
            ->withUpdatedAt($at)
            ->withAttribute('data-sort', 'name')
            ->withClass('card', ' new ', 'card')
            ->withClass('', 'wide');

        self::assertSame(['games', 'Games', 'https://example.com/games', null, [], 'icon-dice', 'Every game.', 120, 4, $at], [$item->key, $item->label, $item->url, $item->route, $item->routeParameters, $item->icon, $item->description, $item->count, $item->newCount, $item->updatedAt]);
        self::assertSame(['data-sort' => 'name', 'class' => 'card new wide'], $item->attributes);
        self::assertSame(['Jeux', 'Games'], [$item->withLabel('Jeux')->label, $item->label]);
        self::assertSame(['data-sort' => 'date'], $item->withAttribute('class', null)->withAttribute('data-sort', 'date')->attributes);
        self::assertSame([false, false, false], [$item->current, $item->currentAncestor, $item->isActive()]);
    }

    public function testARouteAndAnAddressReplaceEachOther(): void
    {
        $routed = MenuItem::of('game', 'Game')->withUrl('/x')->withRoute('game_show', ['slug' => 'chess', 'page' => 2]);

        self::assertSame(['game_show', ['slug' => 'chess', 'page' => 2], null], [$routed->route, $routed->routeParameters, $routed->url]);
        self::assertSame([null, [], '/y'], [$routed->withUrl('/y')->route, $routed->withUrl('/y')->routeParameters, $routed->withUrl('/y')->url]);
        self::assertSame([], MenuItem::of('a', 'A')->withRoute('home')->routeParameters);
    }

    public function testChildrenAreFoundAndChangedFirst(): void
    {
        $tree = MenuItem::of('root', 'Root')->withChildren(
            MenuItem::of('a', 'A')->withChild(MenuItem::of('a1', 'A1')),
            MenuItem::of('b', 'B'),
        )->withChild(MenuItem::of('c', 'C'));

        self::assertSame(['a', 'b', 'c'], array_map(static fn (MenuItem $item): string => $item->key, $tree->children));
        self::assertSame([0], array_keys(MenuItem::of('root', 'Root')->withChildren(...['first' => MenuItem::of('a', 'A')])->children));
        self::assertSame([0], array_keys(Menu::of('main', ['first' => MenuItem::of('a', 'A')])->items));
        self::assertSame('A1', $tree->find('a1')?->label);
        self::assertSame($tree, $tree->find('root'));
        self::assertSame('C', $tree->find('c')?->label);
        self::assertNull($tree->find('z'));

        $visited = [];
        $counted = $tree->map(static function (MenuItem $item) use (&$visited): MenuItem {
            $visited[] = $item->key;

            return $item->withCount(array_sum(array_map(static fn (MenuItem $child): int => $child->count ?? 0, $item->children)) + 1);
        });

        self::assertSame(['a1', 'a', 'b', 'c', 'root'], $visited);
        self::assertSame([5, 2], [$counted->count, $counted->find('a')?->count]);
    }

    public function testAMapChecksTheKeysOfRenamedChildrenOnly(): void
    {
        $tree = MenuItem::of('root', 'Root')->withChildren(
            MenuItem::of('a', 'A')->withChild(MenuItem::of('a1', 'A1')),
            MenuItem::of('b', 'B'),
        );

        self::assertSame($tree, $tree->map(static fn (MenuItem $item): MenuItem => $item), 'nothing changed, nothing rebuilt');
        $a = $tree->find('a');
        $unchangedA = $tree->map(static fn (MenuItem $item): MenuItem => 'b' === $item->key ? $item->withLabel('Bee') : $item);
        self::assertSame($a, $unchangedA->find('a'), 'a branch that came back the same is kept');
        self::assertSame(['Bee', 'A1'], [$unchangedA->find('b')?->label, $unchangedA->find('a1')?->label]);
        self::assertNotSame($tree, $unchangedA);
        self::assertSame('B', $tree->find('b')?->label, 'the tree mapped is left as it was');

        $renamed = $tree->map(static fn (MenuItem $item): MenuItem => 'b' === $item->key ? MenuItem::of('c', 'C') : $item);
        self::assertSame(['a', 'c'], array_map(static fn (MenuItem $item): string => $item->key, $renamed->children));

        foreach ([
            static fn (MenuItem $item): MenuItem => 'b' === $item->key ? MenuItem::of('a', 'Twin') : $item,
            static fn (MenuItem $item): MenuItem => \in_array($item->key, ['a', 'b'], true) ? MenuItem::of('same', 'Same') : $item,
        ] as $twins) {
            try {
                $tree->map($twins);
                self::fail('A child was renamed to the key of its sibling.');
            } catch (InvalidMenu $invalidMenu) {
                self::assertSame('The children of the menu item "root" have the same key twice.', $invalidMenu->getMessage());
            }
        }
    }

    public function testCurrentAndAncestorAreMarked(): void
    {
        $item = MenuItem::of('a', 'A');

        self::assertSame([true, false, true], [$item->markCurrent()->current, $item->markCurrent()->currentAncestor, $item->markCurrent()->isActive()]);
        self::assertSame([false, true, true], [$item->markCurrentAncestor()->current, $item->markCurrentAncestor()->currentAncestor, $item->markCurrentAncestor()->isActive()]);
    }

    public function testCountsMayBeNone(): void
    {
        self::assertSame([null, null], [MenuItem::of('a', 'A')->withCount(null)->count, MenuItem::of('a', 'A')->withCount(null)->newCount]);
        self::assertSame([null, 3], [MenuItem::of('a', 'A')->withCount(null, 3)->count, MenuItem::of('a', 'A')->withCount(null, 3)->newCount]);
        self::assertSame([0, 0], [MenuItem::of('a', 'A')->withCount(0, 0)->count, MenuItem::of('a', 'A')->withCount(0, 0)->newCount]);
        self::assertSame(3, MenuItem::of('a', 'A')->withCount(3, 3)->newCount);
    }

    /**
     * @return iterable<string, array{int|null, int|null}>
     */
    public static function badCounts(): iterable
    {
        yield 'negative' => [-1, null];
        yield 'negative new' => [3, -1];
        yield 'negative new alone' => [null, -1];
        yield 'more new than all' => [3, 4];
    }

    #[DataProvider('badCounts')]
    public function testBadCountsAreRefused(?int $count, ?int $newCount): void
    {
        $this->expectException(InvalidMenu::class);
        $this->expectExceptionMessageIsOrContains('The counts of the menu item "a" are zero or more, and the new ones no more than all.');

        MenuItem::of('a', 'A')->withCount($count, $newCount);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function badItems(): iterable
    {
        yield 'an empty key' => ['', 'A', '"" is not a key of a menu item: up to 100 letters, digits, "_", "-" and ".".'];
        yield 'a key with a space' => ['a b', 'A', '"a b" is not a key of a menu item'];
        yield 'a key starting with a dot' => ['.a', 'A', '".a" is not a key of a menu item'];
        yield 'a key too long' => [str_repeat('a', 101), 'A', 'is not a key of a menu item'];
        yield 'a blank label' => ['a', ' ', 'The menu item "a" has no label.'];
    }

    #[DataProvider('badItems')]
    public function testBadItemsAreRefused(string $key, string $label, string $message): void
    {
        $this->expectException(InvalidMenu::class);
        $this->expectExceptionMessageIsOrContains($message);

        MenuItem::of($key, $label);
    }

    public function testAnAddressIsOnTheSiteOrOfASafeScheme(): void
    {
        foreach (['/games', '//cdn.example.com/a', '#top', '?page=2', 'games/chess', 'https://example.com/', 'HTTP://example.com/', 'mailto:ada@example.com', 'tel:+15145550100', '/go?to=javascript:alert(1)', 'games/data:1'] as $url) {
            self::assertSame($url, MenuItem::of('a', 'A')->withUrl($url)->url);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badUrls(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)', 'The address of the menu item "a" is on the site, or its scheme is one of http, https, mailto, tel: "javascript" is not.'];
        yield 'javascript in capitals' => ['JavaScript:alert(1)', '"JavaScript" is not.'];
        yield 'data' => ['data:text/html,<script>alert(1)</script>', '"data" is not.'];
        yield 'a long scheme, cut' => ['abcdefghijklmnopqrstuvwxyz:1', ': "abcdefghijklmnopqrst" is not.'];
        yield 'a scheme cut by a line break' => ["java\nscript:alert(1)", 'The address of the menu item "a" is empty or holds a space or a control character.'];
        yield 'a scheme cut by a tab' => ["java\tscript:alert(1)", 'holds a space or a control character'];
        yield 'a leading space' => [' javascript:alert(1)', 'holds a space or a control character'];
        yield 'empty' => ['', 'is empty'];
    }

    #[DataProvider('badUrls')]
    public function testOtherAddressesAreRefused(string $url, string $message): void
    {
        $this->expectException(InvalidMenu::class);
        $this->expectExceptionMessageIsOrContains($message);

        MenuItem::of('a', 'A')->withUrl($url);
    }

    public function testAttributeNamesAreChecked(): void
    {
        self::assertSame(['data-sort' => 'a', 'aria-label' => 'b', 'xml:lang' => 'c', 'x.y_z' => 'd', 'don' => 'e'], MenuItem::of('a', 'A')->withAttribute('data-sort', 'a')->withAttribute('aria-label', 'b')->withAttribute('xml:lang', 'c')->withAttribute('x.y_z', 'd')->withAttribute('don', 'e')->attributes);
        self::assertSame(['open' => '', 'Optimum' => '1'], MenuItem::of('a', 'A')->withAttribute('open', '')->withAttribute('Optimum', '1')->attributes);

        try {
            MenuItem::of('a', 'A')->withAttribute('é'.str_repeat('x', 99).'yz', 'x');
            self::fail('A name of 102 characters was taken.');
        } catch (InvalidMenu $invalidMenu) {
            self::assertStringStartsWith('"é'.str_repeat('x', 99).'" is not an attribute', $invalidMenu->getMessage());
        }

        foreach (['onclick', 'OnMouseOver', 'on', 'one', '', '1a', 'a b', 'a"', 'a>', str_repeat('a', 101)] as $name) {
            try {
                MenuItem::of('a', 'A')->withAttribute($name, 'x');
                self::fail(\sprintf('The attribute "%s" was taken.', $name));
            } catch (InvalidMenu $invalidMenu) {
                self::assertStringContainsString('is not an attribute a menu item may have: a letter, then letters, digits, "_", "-", "." and ":", and no event handler.', $invalidMenu->getMessage());
            }
        }
    }

    public function testKeysAreChecked(): void
    {
        self::assertSame(str_repeat('a', 100), MenuItem::of(str_repeat('a', 100), 'A')->key);
        self::assertSame('A_b-c.9', MenuItem::of('A_b-c.9', 'A')->key);

        try {
            MenuItem::of(str_repeat('é', 150), 'A');
            self::fail('A bad key was taken.');
        } catch (InvalidMenu $invalidMenu) {
            self::assertSame('"'.str_repeat('é', 100).'" is not a key of a menu item: up to 100 letters, digits, "_", "-" and ".".', $invalidMenu->getMessage());
        }

        try {
            MenuItem::of('a', 'A')->withLabel('');
            self::fail('An empty label was taken.');
        } catch (InvalidMenu) {
        }

        $this->expectExceptionMessageIsOrContains('The children of the menu item "a" have the same key twice.');

        MenuItem::of('a', 'A')->withChild(MenuItem::of('b', 'B'))->withChild(MenuItem::of('b', 'Other'));
    }

    public function testAMenuHasItsTrailToTheCurrentItem(): void
    {
        $menu = Menu::of('main', [
            MenuItem::of('home', 'Home'),
            MenuItem::of('games', 'Games')->markCurrentAncestor()->withChildren(
                MenuItem::of('board', 'Board')->markCurrent()->withChild(MenuItem::of('chess', 'Chess')->markCurrentAncestor()),
                MenuItem::of('cards', 'Cards'),
            ),
        ], 'menu');

        self::assertSame(['games', 'board'], array_map(static fn (MenuItem $item): string => $item->key, $menu->breadcrumb()));
        self::assertSame([], Menu::of('main', [MenuItem::of('home', 'Home')])->breadcrumb());
        self::assertSame(['main', 'menu', null], [$menu->name, $menu->translationDomain, Menu::of('x')->translationDomain]);
        self::assertSame('Chess', $menu->find('chess')?->label);
        self::assertNull($menu->find('z'));
        self::assertSame(['HOME', 'GAMES', 'BOARD'], array_map(static fn (MenuItem $item): string => $item->label, [...$menu->map(static fn (MenuItem $item): MenuItem => $item->withLabel(strtoupper($item->label)))->items, $menu->map(static fn (MenuItem $item): MenuItem => $item->withLabel(strtoupper($item->label)))->find('board') ?? MenuItem::of('z', 'Z')]));
        self::assertSame('menu', $menu->map(static fn (MenuItem $item): MenuItem => $item)->translationDomain);
    }

    public function testAMenusKeysAreUnique(): void
    {
        $this->expectException(InvalidMenu::class);
        $this->expectExceptionMessageIsOrContains('The items of the menu "main" have the same key twice.');

        Menu::of('main', [MenuItem::of('a', 'A'), MenuItem::of('a', 'B')]);
    }
}
