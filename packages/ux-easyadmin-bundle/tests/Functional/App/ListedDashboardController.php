<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Tests\Functional\App;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Override;
use Phil\UxEasyAdminBundle\PhilUxEasyAdminBundle;
use Symfony\Component\HttpFoundation\Response;

/**
 * A dashboard that lists the theme in its own assets: the page links it once.
 */
#[AdminDashboard(routePath: '/listed', routeName: 'listed')]
final class ListedDashboardController extends AbstractDashboardController
{
    #[Override]
    public function index(): Response
    {
        return $this->render('@EasyAdmin/page/content.html.twig');
    }

    #[Override]
    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile(PhilUxEasyAdminBundle::STYLESHEET);
    }
}
