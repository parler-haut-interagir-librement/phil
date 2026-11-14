<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * The quarantine reached from an EasyAdmin back office: its page keeps its own routes, role, CSRF token and layout —
 * `phil_upload_scan.admin.layout`, which the application points to a template of its back office —, goes through the
 * quarantine's inventory, and the dashboard's menu links to it:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield MenuItem::section('Security');
 *         yield QuarantineAdmin::menuItem();
 *     }
 */
final class QuarantineAdmin
{
    private function __construct()
    {
    }

    /**
     * The link to the quarantine's files.
     */
    public static function menuItem(string|TranslatableInterface $label = 'Quarantine', ?string $icon = 'fa fa-biohazard'): RouteMenuItem
    {
        return MenuItem::linkToRoute($label, $icon, QuarantineAdminController::LIST_ROUTE);
    }
}
