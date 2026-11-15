<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Phil\TermsBundle\Infrastructure\Http\TermsAdminController;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The administration of the terms reached from an EasyAdmin back office: its pages keep their own routes, role, forms
 * and layout — the bundle's `admin.layout`, which the application points to a template of its back office —, and the
 * dashboard's menu links to them:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield from TermsAdmin::menuItems();
 *     }
 */
final class TermsAdmin
{
    private function __construct()
    {
    }

    /**
     * The link to the policies and their versions, labelled in the `phil_terms` domain.
     *
     * @return list<RouteMenuItem>
     */
    public static function menuItems(): array
    {
        return [
            MenuItem::linkToRoute(new TranslatableMessage('menu.terms', [], 'phil_terms'), 'fa fa-file-contract', TermsAdminController::LIST_ROUTE),
        ];
    }
}
