<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Unit\Infrastructure;

use Phil\MenuBundle\Application\MenuBuilder;
use Phil\MenuBundle\Application\MenuContext;
use Phil\MenuBundle\Application\Menus;
use Phil\MenuBundle\Domain\Menu;
use Phil\MenuBundle\Domain\MenuItem;
use Phil\MenuBundle\Infrastructure\Twig\MenuExtension;
use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\PageContext;
use Phil\StructuredDataBundle\Infrastructure\Menu\MenuBreadcrumbProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(MenuBreadcrumbProvider::class)]
final class MenuBreadcrumbProviderTest extends TestCase
{
    public function testTheBreadcrumbOfTheMenuIsThePagesOne(): void
    {
        $page = new PageContext('https://example.com/', 'https://example.com/jeux/des');

        self::assertSame([
            [
                '@type' => 'BreadcrumbList',
                '@id' => 'https://example.com/jeux/des#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Jeux', 'item' => 'https://example.com/jeux'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Dés', 'item' => 'https://example.com/jeux/des'],
                ],
            ],
            ['@type' => 'WebPage', '@id' => 'https://example.com/jeux/des#webpage', 'breadcrumb' => ['@id' => 'https://example.com/jeux/des#breadcrumb']],
        ], $this->nodes(new MenuBreadcrumbProvider($this->menus('/jeux/des'), 'main', true), $page));
        self::assertCount(1, $this->nodes(new MenuBreadcrumbProvider($this->menus('/jeux/des'), 'main', false), $page));
        self::assertSame([['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Jeux', 'item' => 'https://example.com/jeux'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Dés', 'item' => 'https://example.com/jeux/des'],
        ]]], $this->nodes(new MenuBreadcrumbProvider($this->menus('/jeux/des'), 'main', true), new PageContext()));
    }

    public function testAPageOutsideTheMenuHasNoBreadcrumb(): void
    {
        self::assertSame([], $this->nodes(new MenuBreadcrumbProvider($this->menus('/ailleurs'), 'main', true), new PageContext('https://example.com/', 'https://example.com/ailleurs')));
    }

    public function testAnItemWithoutAddressIsNamedWithoutOne(): void
    {
        $page = new PageContext('https://example.com/', 'https://example.com/jeux/des');
        $games = MenuItem::of('games', 'Jeux')->withChildren(MenuItem::of('dice', 'Dés')->withUrl('/jeux/des'));

        self::assertSame([['@type' => 'BreadcrumbList', '@id' => 'https://example.com/jeux/des#breadcrumb', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Jeux'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Dés', 'item' => 'https://example.com/jeux/des'],
        ]]], $this->nodes(new MenuBreadcrumbProvider($this->menus('/jeux/des', $games), 'main', false), $page));
    }

    private function menus(string $path, ?MenuItem $games = null): MenuExtension
    {
        $builder = new readonly class($games ?? MenuItem::of('games', 'Jeux')->withUrl('/jeux')->withChildren(MenuItem::of('dice', 'Dés')->withUrl('/jeux/des'))) implements MenuBuilder {
            public function __construct(private MenuItem $games)
            {
            }

            public function name(): string
            {
                return 'main';
            }

            public function build(MenuContext $context): Menu
            {
                return Menu::of('main', [$this->games]);
            }
        };
        $requests = new RequestStack([Request::create('https://example.com'.$path)]);

        return new MenuExtension(new Menus([$builder]), $requests);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nodes(MenuBreadcrumbProvider $provider, PageContext $page): array
    {
        $nodes = [];

        foreach ($provider->nodes($page) as $node) {
            self::assertInstanceOf(Node::class, $node);
            $nodes[] = $node->toArray();
        }

        return $nodes;
    }
}
