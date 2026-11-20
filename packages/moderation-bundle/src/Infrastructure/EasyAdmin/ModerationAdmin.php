<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use Phil\ModerationBundle\Infrastructure\Http\AdminController;
use Symfony\Component\HttpFoundation\Request;

/**
 * The pages of the moderation inside an EasyAdmin back office. When EasyAdminBundle is registered, the bundle grafts
 * `AdminController` on every dashboard through `#[AdminRoute]`: the same pages, roles and forms, through the same
 * services, rendered in the dashboard's layout, under routes EasyAdmin names after the dashboard's —
 * `admin_moderation_queue` under a dashboard whose route is `admin`. The dashboard's menu links to the queue:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield ModerationAdmin::menuItem('admin');
 *     }
 */
final class ModerationAdmin
{
    /** The request attribute EasyAdmin keeps its context in, on the routes of a dashboard. */
    public const string CONTEXT_ATTRIBUTE = 'easyadmin_context';

    /** The layout of the pages in a back office: EasyAdmin's content page, its title and main blocks filled. */
    public const string LAYOUT = '@PhilModeration/easyadmin/layout.html.twig';

    /** The part of the routes' names after the dashboard's, as `#[AdminRoute]` on `AdminController` names them. */
    public const array ROUTES = [
        AdminController::QUEUE_ROUTE => '_moderation_queue',
        AdminController::ENTRY_ROUTE => '_moderation_entry',
        AdminController::DECIDE_ROUTE => '_moderation_decide',
        AdminController::MEMBER_ROUTE => '_moderation_member',
        AdminController::NOTE_ROUTE => '_moderation_note',
        AdminController::NOTE_REMOVE_ROUTE => '_moderation_note_remove',
    ];

    private function __construct()
    {
    }

    /**
     * The link to the queue under the dashboard whose route is given.
     */
    public static function menuItem(string $dashboardRoute = 'admin', string $label = 'Moderation', ?string $icon = 'fa fa-flag'): RouteMenuItem
    {
        return MenuItem::linkToRoute($label, $icon, $dashboardRoute.self::ROUTES[AdminController::QUEUE_ROUTE]);
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
