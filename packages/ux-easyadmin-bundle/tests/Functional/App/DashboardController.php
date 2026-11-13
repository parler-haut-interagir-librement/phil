<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Tests\Functional\App;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Override;
use Symfony\Component\HttpFoundation\Response;

/**
 * A dashboard with one stylesheet of its own, served at `/admin`; a second one, at `/listed`, links the theme itself.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    #[Override]
    public function index(): Response
    {
        return $this->render('@EasyAdmin/page/content.html.twig');
    }

    #[Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Back-office');
    }

    #[Override]
    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('styles/admin.css');
    }
}
