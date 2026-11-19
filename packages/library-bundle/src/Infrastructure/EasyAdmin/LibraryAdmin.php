<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Phil\LibraryBundle\Infrastructure\Http\AdminController;
use Phil\LibraryBundle\Infrastructure\Http\CatalogueController;
use Phil\LibraryBundle\Infrastructure\Http\DeskController;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The library reached from an EasyAdmin back office: its pages keep their own routes, role, forms and layout — the
 * bundle's `pages.layout`, which the application may point to a template of its back office —, and the dashboard's
 * menu links to them:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield from LibraryAdmin::menuItems();
 *     }
 */
final class LibraryAdmin
{
    private function __construct()
    {
    }

    /**
     * The links to the desk, the catalogue, a new title and the loans overdue, labelled in the `phil_library` domain.
     *
     * @return list<RouteMenuItem>
     */
    public static function menuItems(): array
    {
        return [
            MenuItem::linkToRoute(new TranslatableMessage('menu.desk', [], 'phil_library'), 'fa fa-barcode', DeskController::ROUTE),
            MenuItem::linkToRoute(new TranslatableMessage('menu.catalogue', [], 'phil_library'), 'fa fa-book', CatalogueController::SEARCH_ROUTE),
            MenuItem::linkToRoute(new TranslatableMessage('menu.new_title', [], 'phil_library'), 'fa fa-plus', AdminController::NEW_TITLE_ROUTE),
            MenuItem::linkToRoute(new TranslatableMessage('menu.overdue', [], 'phil_library'), 'fa fa-clock', AdminController::OVERDUE_ROUTE),
        ];
    }
}
