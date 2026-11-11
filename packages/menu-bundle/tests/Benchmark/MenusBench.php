<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Tests\Benchmark;

use Closure;
use Generator;
use Override;
use Phil\MenuBundle\Application\MenuBuilder;
use Phil\MenuBundle\Application\MenuContext;
use Phil\MenuBundle\Application\MenuDecorator;
use Phil\MenuBundle\Application\Menus;
use Phil\MenuBundle\Domain\Menu;
use Phil\MenuBundle\Domain\MenuItem;

/**
 * Each menu Twig function of a page calls Menus::get, which walks the whole tree once per decorator and once more to
 * mark the current item, rebuilding every item on each pass.
 */
final class MenusBench
{
    /**
     * @var array<string, Menus>
     */
    private array $menus = [];

    private readonly MenuContext $context;

    public function __construct()
    {
        $decorators = [$this->decorator(static fn (MenuItem $item): MenuItem => $item->withClass('menu-item')), $this->decorator(static fn (MenuItem $item): MenuItem => $item->withAttribute('data-key', $item->key))];

        foreach (['10' => [10], '100' => [10, 10], '1000' => [10, 10, 10]] as $size => $shape) {
            $builder = $this->builder(Menu::of('main', self::level($shape, 'section'), 'messages'));
            $this->menus[$size.'/0'] = new Menus([$builder]);
            $this->menus[$size.'/2'] = new Menus([$builder], $decorators);
        }

        // The visitor is on the last leaf, so the marking walks every branch before reaching it.
        $this->context = new MenuContext('page', ['slug' => 'section-9-9-9'], '/section-9-9-9');
    }

    /**
     * @ParamProviders({"sizes", "decoratorCounts"})
     *
     * @param array{size: string, decorators: int} $params
     */
    public function benchGet(array $params): void
    {
        $this->menus[$params['size'].'/'.$params['decorators']]->get('main', $this->context);
    }

    /**
     * @return Generator<string, array{size: string}>
     */
    public function sizes(): Generator
    {
        yield '10 items' => ['size' => '10'];
        yield '110 items, 2 levels' => ['size' => '100'];
        yield '1110 items, 3 levels' => ['size' => '1000'];
    }

    /**
     * @return Generator<string, array{decorators: int}>
     */
    public function decoratorCounts(): Generator
    {
        yield 'no decorator' => ['decorators' => 0];
        yield '2 decorators' => ['decorators' => 2];
    }

    /**
     * @param list<int> $shape the number of items at each level
     *
     * @return list<MenuItem>
     */
    private static function level(array $shape, string $prefix): array
    {
        $count = array_shift($shape);
        $items = [];

        for ($index = 0; $index < $count; ++$index) {
            $key = $prefix.'-'.$index;
            $item = MenuItem::of($key, 'menu.'.$key)->withRoute('page', ['slug' => $key])->withIcon('icon-'.$index);
            $items[] = [] === $shape ? $item : $item->withChildren(...self::level($shape, $key));
        }

        return $items;
    }

    private function builder(Menu $menu): MenuBuilder
    {
        return new readonly class($menu) implements MenuBuilder {
            public function __construct(
                private Menu $menu,
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
                return $this->menu;
            }
        };
    }

    /**
     * @param Closure(MenuItem): MenuItem $change
     */
    private function decorator(Closure $change): MenuDecorator
    {
        return new readonly class($change) implements MenuDecorator {
            /**
             * @param Closure(MenuItem): MenuItem $change
             */
            public function __construct(
                private Closure $change,
            ) {
            }

            #[Override]
            public function supports(string $menu): bool
            {
                return 'main' === $menu;
            }

            #[Override]
            public function decorate(MenuItem $item, MenuContext $context): MenuItem
            {
                return ($this->change)($item);
            }
        };
    }
}
