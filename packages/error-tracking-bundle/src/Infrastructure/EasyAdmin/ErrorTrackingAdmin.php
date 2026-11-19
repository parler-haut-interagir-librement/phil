<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use Phil\ErrorTrackingBundle\Infrastructure\Http\AdminController;
use Symfony\Component\HttpFoundation\Request;

/**
 * The administration pages of the errors inside an EasyAdmin back office. When EasyAdminBundle is registered, the
 * bundle grafts `AdminController` on every dashboard through `#[AdminRoute]`: the same pages, role and forms, rendered
 * in the dashboard's layout, under routes EasyAdmin names after the dashboard's — `admin_errors_groups` under a
 * dashboard whose route is `admin`. The dashboard's menu links to the list:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield ErrorTrackingAdmin::menuItem('admin');
 *     }
 */
final class ErrorTrackingAdmin
{
    /** The request attribute EasyAdmin keeps its context in, on the routes of a dashboard. */
    public const string CONTEXT_ATTRIBUTE = 'easyadmin_context';

    /** The layout of the pages in a back office: EasyAdmin's content page, its title and main blocks filled. */
    public const string LAYOUT = '@PhilErrorTracking/easyadmin/layout.html.twig';

    /** The part of the routes' names after the dashboard's, as `#[AdminRoute]` on `AdminController` names them. */
    public const array ROUTES = [
        AdminController::INDEX_ROUTE => '_errors_groups',
        AdminController::SHOW_ROUTE => '_errors_group',
        AdminController::STATUS_ROUTE => '_errors_group_status',
    ];

    private function __construct()
    {
    }

    /**
     * The link to the list of the groups under the dashboard whose route is given.
     */
    public static function menuItem(string $dashboardRoute = 'admin', string $label = 'Errors', ?string $icon = 'fa fa-bug'): RouteMenuItem
    {
        return MenuItem::linkToRoute($label, $icon, $dashboardRoute.self::ROUTES[AdminController::INDEX_ROUTE]);
    }

    /**
     * The route of the dashboard the request was routed through, or `null` outside a back office.
     */
    public static function dashboardRoute(Request $request): ?string
    {
        $context = $request->attributes->get(self::CONTEXT_ATTRIBUTE);

        return $context instanceof AdminContextInterface ? $context->getDashboardRouteName() : null;
    }
}
