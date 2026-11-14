<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard as Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Override;
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditViewerAdmin;

/**
 * The back office of the tests: its menu holds the links of the audit log.
 */
#[Dashboard(routePath: '/admin', routeName: 'admin')]
final class AdminDashboard extends AbstractDashboardController
{
    #[Override]
    public function configureMenuItems(): iterable
    {
        yield from AuditViewerAdmin::menuItems('admin');
    }
}
