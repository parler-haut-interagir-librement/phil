<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Functional\BackOffice;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Override;
use Phil\ModerationBundle\Infrastructure\EasyAdmin\ModerationAdmin;

/**
 * The back office of the tests: its menu holds the link to the queue.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class Dashboard extends AbstractDashboardController
{
    #[Override]
    public function configureMenuItems(): iterable
    {
        yield ModerationAdmin::menuItem();
    }
}
