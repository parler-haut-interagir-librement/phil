<?php

declare(strict_types=1);

use Phil\LibraryBundle\Infrastructure\Http\AdminController;
use Phil\LibraryBundle\Infrastructure\Http\CatalogueController;
use Phil\LibraryBundle\Infrastructure\Http\DeskController;
use Phil\LibraryBundle\Infrastructure\Http\MemberLoansController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The pages of the library: the catalogue and its titles, the member's loans, the desk and the administration. The
 * application mounts them under a prefix, one per locale if it likes:
 *
 *     # config/routes/phil_library.yaml
 *     phil_library:
 *         resource: '@PhilLibraryBundle/config/routes.php'
 *         prefix: /library
 */
return static function (RoutingConfigurator $routes): void {
    $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    $routes->add(CatalogueController::SEARCH_ROUTE, '/')->controller([CatalogueController::class, 'search'])->methods(['GET']);
    $routes->add(CatalogueController::TITLE_ROUTE, '/titles/{id}')->controller([CatalogueController::class, 'title'])->requirements(['id' => $uuid])->methods(['GET']);
    $routes->add(MemberLoansController::ROUTE, '/my-loans')->controller(MemberLoansController::class)->methods(['GET']);
    $routes->add(DeskController::ROUTE, '/desk')->controller(DeskController::class)->methods(['GET', 'POST']);
    $routes->add(AdminController::NEW_TITLE_ROUTE, '/admin/titles/new')->controller([AdminController::class, 'newTitle'])->methods(['GET', 'POST']);
    $routes->add(AdminController::EDIT_TITLE_ROUTE, '/admin/titles/{id}')->controller([AdminController::class, 'editTitle'])->requirements(['id' => $uuid])->methods(['GET', 'POST']);
    $routes->add(AdminController::NEW_COPY_ROUTE, '/admin/titles/{id}/copies/new')->controller([AdminController::class, 'newCopy'])->requirements(['id' => $uuid])->methods(['GET', 'POST']);
    $routes->add(AdminController::OVERDUE_ROUTE, '/admin/overdue')->controller([AdminController::class, 'overdue'])->methods(['GET']);
};
