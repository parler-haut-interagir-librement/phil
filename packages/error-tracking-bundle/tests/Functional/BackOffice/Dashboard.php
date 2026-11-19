<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Functional\BackOffice;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Override;
use Phil\ErrorTrackingBundle\Infrastructure\EasyAdmin\ErrorTrackingAdmin;

/**
 * The back office of the tests: its menu holds the link to the errors.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class Dashboard extends AbstractDashboardController
{
    #[Override]
    public function configureMenuItems(): iterable
    {
        yield ErrorTrackingAdmin::menuItem();
    }
}
