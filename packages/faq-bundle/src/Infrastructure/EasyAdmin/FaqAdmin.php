<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Phil\FaqBundle\Infrastructure\Http\FaqAdminController;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The administration of the questions reached from an EasyAdmin back office: its pages keep their own routes, role,
 * forms and layout — the bundle's `admin.layout`, which the application points to a template of its back office —, and
 * the dashboard's menu links to them:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield MenuItem::section('Questions');
 *         yield from FaqAdmin::menuItems();
 *     }
 */
final class FaqAdmin
{
    private function __construct()
    {
    }

    /**
     * The links to the sections and questions, and to the votes and the searches without answer, labelled in the
     * `phil_faq` domain.
     *
     * @return list<RouteMenuItem>
     */
    public static function menuItems(): array
    {
        return [
            MenuItem::linkToRoute(new TranslatableMessage('admin.menu.questions', [], 'phil_faq'), 'fa fa-circle-question', FaqAdminController::INDEX_ROUTE),
            MenuItem::linkToRoute(new TranslatableMessage('admin.menu.statistics', [], 'phil_faq'), 'fa fa-chart-simple', FaqAdminController::STATISTICS_ROUTE),
        ];
    }
}
