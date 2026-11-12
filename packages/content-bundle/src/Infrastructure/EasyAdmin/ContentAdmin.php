<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Phil\ContentBundle\Infrastructure\Http\ContentAdminController;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The administration of the contents reached from an EasyAdmin back office: its pages keep their own routes, role,
 * forms and layout — the bundle's `admin.layout`, which the application points to a template of its back office —, go
 * through the bundle's application services, and the dashboard's menu links to them:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield from ContentAdmin::menuItems();
 *     }
 */
final class ContentAdmin
{
    private function __construct()
    {
    }

    /**
     * The link to the list of contents, labelled in the `phil_content` domain.
     *
     * @return list<RouteMenuItem>
     */
    public static function menuItems(): array
    {
        return [
            MenuItem::linkToRoute(new TranslatableMessage('menu.contents', [], 'phil_content'), 'fa fa-file-lines', ContentAdminController::LIST_ROUTE),
        ];
    }
}
