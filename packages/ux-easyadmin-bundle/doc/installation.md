# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `easycorp/easyadmin-bundle` | ^5.10 | The back-office, its cascade layers and its context |
| `symfony/asset-mapper` | ^8.2 | The stylesheet served as `phil-ux-easyadmin/phil-ux-easyadmin.css` |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel` | ^8.2 | The bundle class, its configuration and its listener |
| `phil/ux-css` | ^0.5 | The tokens the theme reads |

```sh
composer require phil/ux-easyadmin-bundle
```

## EasyAdmin on the sites of PHIL bundles

A site built on PHIL bundles has EasyAdmin: the administration pages of the bundles are EasyAdmin pages — CRUD
controllers and custom pages of the site's dashboards —, so the site requires `easycorp/easyadmin-bundle` and declares
at least one dashboard. A dashboard is a controller of the site:

```php
// src/Controller/Admin/DashboardController.php
namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Back-office');
    }

    public function configureAssets(): Assets
    {
        // The site's own back-office stylesheet, written outside any cascade layer: it wins over the theme.
        return Assets::new()->addCssFile('styles/admin.css');
    }
}
```

`configureAssets()` lists what the dashboard adds to every one of its pages; a CRUD controller's own
`configureAssets()` adds to it for its pages. With `theme` on, the bundle links the theme after both lists, so the
dashboard lists only the site's stylesheet, if it has one. The theme follows the dashboard's colour scheme —
`Dashboard::new()->setDefaultColorScheme()` and the scheme each administrator picks — with no setting of its own.

## The recipe

The Flex recipe of the bundle, `recipe/0.1/` in the package, registers it in `config/bundles.php` and writes
`config/packages/phil_ux_easy_admin.yaml`. Flex reads it from the recipes repository of PHIL once the application lists
that repository in `extra.symfony.endpoint`. Without it, register the bundle by hand:

```php
// config/bundles.php
return [
    // ...
    EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
    Phil\UxEasyAdminBundle\PhilUxEasyAdminBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/phil_ux_easy_admin.yaml
phil_ux_easy_admin:
    theme: true    # false: each dashboard lists the stylesheet itself
```

With `theme: false`, a dashboard that wants the theme lists it in its assets:

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use Phil\UxEasyAdminBundle\PhilUxEasyAdminBundle;

public function configureAssets(): Assets
{
    return Assets::new()->addCssFile(PhilUxEasyAdminBundle::STYLESHEET);
}
```

A dashboard that lists it while `theme` is on links it once.

## The site and its back-office

The back-office is the site's EasyAdmin dashboards. The site's own pages take the stylesheet of phil/ux-css and the
recipes of the PHIL UX kit, which `ux:install` copies into the application; EasyAdmin renders its pages and forms with
its own templates, so the theme changes only the values of EasyAdmin's variables. A site that changes its tokens in its
own stylesheet lists that stylesheet in the dashboard's `configureAssets()` too, for the back-office to follow.
