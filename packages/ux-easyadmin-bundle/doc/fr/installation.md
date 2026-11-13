<!-- phil-dev source-sha256=ff2598fe5ed9fb1db608b2784fb5d88c3315bf3457c815d6abecb569571ac3cd -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `easycorp/easyadmin-bundle` | ^5.10 | L'administration, ses couches de cascade et son contexte |
| `symfony/asset-mapper` | ^8.2 | La feuille de style servie sous `phil-ux-easyadmin/phil-ux-easyadmin.css` |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel` | ^8.2 | La classe du bundle, sa configuration et son écouteur |
| `phil/ux-css` | ^0.5 | Les jetons que le thème lit |

```sh
composer require phil/ux-easyadmin-bundle
```

## EasyAdmin sur les sites des bundles de PHIL

Un site bâti sur les bundles de PHIL a EasyAdmin : les pages d'administration des bundles sont des pages EasyAdmin —
contrôleurs CRUD et pages personnalisées des tableaux de bord du site —, si bien que le site exige
`easycorp/easyadmin-bundle` et déclare au moins un tableau de bord. Un tableau de bord est un contrôleur du site :

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

`configureAssets()` liste ce que le tableau de bord ajoute à chacune de ses pages ; le `configureAssets()` d'un
contrôleur CRUD s'y ajoute pour ses pages. Avec `theme` actif, le bundle lie le thème après les deux listes : le
tableau de bord ne liste donc que la feuille de style du site, s'il en a une. Le thème suit le schéma de couleurs du
tableau de bord — `Dashboard::new()->setDefaultColorScheme()` et le schéma que choisit chaque administrateur — sans
réglage propre.

## La recette

La recette Flex du bundle, `recipe/0.1/` dans le paquet, l'enregistre dans `config/bundles.php` et écrit
`config/packages/phil_ux_easy_admin.yaml`. Flex la lit dans le dépôt de recettes de PHIL dès que l'application liste ce
dépôt dans `extra.symfony.endpoint`. Sans elle, enregistrez le bundle à la main :

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

Avec `theme: false`, un tableau de bord qui veut le thème le liste dans ses ressources :

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use Phil\UxEasyAdminBundle\PhilUxEasyAdminBundle;

public function configureAssets(): Assets
{
    return Assets::new()->addCssFile(PhilUxEasyAdminBundle::STYLESHEET);
}
```

Un tableau de bord qui la liste alors que `theme` est actif ne la lie qu'une fois.

## Le site et son administration

L'administration, ce sont les tableaux de bord EasyAdmin du site. Les pages propres au site prennent la feuille de style
de phil/ux-css et les recettes du kit PHIL UX, que `ux:install` copie dans l'application ; EasyAdmin produit le rendu de
ses pages et de ses formulaires avec ses propres gabarits, si bien que le thème ne change que les valeurs des variables
d'EasyAdmin. Un site qui change ses jetons dans sa propre feuille de style liste aussi cette feuille de style dans le
`configureAssets()` du tableau de bord, pour que l'administration la suive.
