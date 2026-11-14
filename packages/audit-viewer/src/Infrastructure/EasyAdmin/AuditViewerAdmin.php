<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The links of the audit log in a dashboard's menu. EasyAdmin names each page's route after the dashboard's —
 * `admin_audit_log` under a dashboard whose route is `admin` —, so the menu is given that name:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield MenuItem::section('Audit');
 *         yield from AuditViewerAdmin::menuItems('admin');
 *     }
 */
final class AuditViewerAdmin
{
    private function __construct()
    {
    }

    /**
     * The log and the state of the hash chain, labelled in the `phil_audit_viewer` domain.
     *
     * @return list<RouteMenuItem>
     */
    public static function menuItems(string $dashboardRoute = 'admin'): array
    {
        return [
            MenuItem::linkToRoute(new TranslatableMessage('menu.log', [], 'phil_audit_viewer'), 'fa fa-list', $dashboardRoute.'_audit_log'),
            MenuItem::linkToRoute(new TranslatableMessage('menu.chain', [], 'phil_audit_viewer'), 'fa fa-link', $dashboardRoute.'_audit_chain'),
        ];
    }
}
