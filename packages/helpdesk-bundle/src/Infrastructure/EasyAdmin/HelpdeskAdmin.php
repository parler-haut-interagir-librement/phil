<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use Phil\HelpdeskBundle\Infrastructure\Http\AgentController;
use Symfony\Component\HttpFoundation\Request;

/**
 * The pages of the agents inside an EasyAdmin back office. When EasyAdminBundle is registered, the bundle grafts
 * `AgentController` on every dashboard through `#[AdminRoute]`: the same pages, queues and forms, rendered in the
 * dashboard's layout, under routes EasyAdmin names after the dashboard's — `admin_helpdesk_desk` under a dashboard
 * whose route is `admin`. The dashboard's menu links to the desk:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield HelpdeskAdmin::menuItem('admin');
 *     }
 */
final class HelpdeskAdmin
{
    /** The request attribute EasyAdmin keeps its context in, on the routes of a dashboard. */
    public const string CONTEXT_ATTRIBUTE = 'easyadmin_context';

    /** The layout of the pages in a back office: EasyAdmin's content page, its title and main blocks filled. */
    public const string LAYOUT = '@PhilHelpdesk/easyadmin/layout.html.twig';

    /** The part of the routes' names after the dashboard's, as `#[AdminRoute]` on `AgentController` names them. */
    public const array ROUTES = [
        AgentController::DESK_ROUTE => '_helpdesk_desk',
        AgentController::TICKET_ROUTE => '_helpdesk_ticket',
        AgentController::REPLY_ROUTE => '_helpdesk_reply',
        AgentController::ASSIGN_ROUTE => '_helpdesk_assign',
        AgentController::TRANSITION_ROUTE => '_helpdesk_transition',
        AgentController::MOVE_ROUTE => '_helpdesk_move',
    ];

    private function __construct()
    {
    }

    /**
     * The link to the desk under the dashboard whose route is given.
     */
    public static function menuItem(string $dashboardRoute = 'admin', string $label = 'Help desk', ?string $icon = 'fa fa-life-ring'): RouteMenuItem
    {
        return MenuItem::linkToRoute($label, $icon, $dashboardRoute.self::ROUTES[AgentController::DESK_ROUTE]);
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
