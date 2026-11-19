<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Functional\App;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Override;
use Phil\HelpdeskBundle\Infrastructure\EasyAdmin\HelpdeskAdmin;

/**
 * The back office of the tests: its menu holds the link to the desk.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class Dashboard extends AbstractDashboardController
{
    #[Override]
    public function configureMenuItems(): iterable
    {
        yield HelpdeskAdmin::menuItem();
    }
}
