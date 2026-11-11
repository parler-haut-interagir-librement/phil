<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;

/**
 * A reporting dashboard as a page of an EasyAdmin back office: the template a dashboard controller's action renders,
 * and the link of its menu.
 *
 *     #[AdminRoute(path: '/reports/{dashboard}', name: 'reports')]
 *     public function reports(string $dashboard): Response
 *     {
 *         return $this->render(ReportingAdmin::TEMPLATE, ['dashboard' => $dashboard, 'live' => true]);
 *     }
 *
 *     yield ReportingAdmin::menuItem('Sales', 'sales', 'admin_reports');
 */
final class ReportingAdmin
{
    public const string TEMPLATE = '@PhilReporting/easyadmin/dashboard.html.twig';

    /**
     * The link to the dashboard's page, through the route of the action that renders it.
     */
    public static function menuItem(string $label, string $dashboard, string $route, ?string $icon = 'fa fa-chart-column'): RouteMenuItem
    {
        return MenuItem::linkToRoute($label, $icon, $route, ['dashboard' => $dashboard]);
    }
}
