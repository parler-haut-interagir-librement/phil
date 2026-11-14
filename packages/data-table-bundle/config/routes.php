<?php

declare(strict_types=1);

use Phil\DataTableBundle\Infrastructure\Export\TableExports;
use Phil\DataTableBundle\Infrastructure\Http\TableController;
use Phil\DataTableBundle\Infrastructure\Http\TableExportController;
use Phil\DataTableExport\ExportWriters;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The changes of a browser without JavaScript, by plain forms, and the export of a table.
 */
return static function (RoutingConfigurator $routes): void {
    $requirements = ['table' => '[A-Za-z0-9_.-]{1,64}'];

    $routes->add('phil_data_table_action', '/{table}/action/{action}')
        ->controller([TableController::class, 'action'])
        ->methods(['POST'])
        ->requirements([...$requirements, 'action' => '[A-Za-z][A-Za-z0-9_]{0,63}']);

    $routes->add('phil_data_table_edit', '/{table}/edit/{column}')
        ->controller([TableController::class, 'edit'])
        ->methods(['POST'])
        ->requirements([...$requirements, 'column' => '[A-Za-z][A-Za-z0-9_]{0,63}']);

    // The export of a table, a GET that changes nothing, with phil/data-table-export.
    if (class_exists(ExportWriters::class)) {
        $routes->add(TableExports::ROUTE, '/{table}/export.{format}')
            ->controller([TableExportController::class, 'export'])
            ->methods(['GET'])
            ->requirements([...$requirements, 'format' => '[a-z]{1,8}']);
    }
};
