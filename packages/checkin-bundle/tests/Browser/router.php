<?php

declare(strict_types=1);

/*
 * The router of PHP's built-in server for the browser suite: Stimulus and the desk controller's scripts from where
 * they are installed, the page of the desk at `/`, and everything else through the application of the functional
 * suite in its browser environment, whose check-in table is created on the first request.
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Phil\Checkin\CheckpointId;
use Phil\CheckinBundle\Infrastructure\Doctrine\DoctrineCheckInLedger;
use Phil\CheckinBundle\Infrastructure\Http\DeskConfig;
use Phil\CheckinBundle\Tests\Functional\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

$package = dirname(__DIR__, 2);
$vendor = array_find([$package.'/../../vendor', $package.'/vendor'], static fn (string $directory): bool => is_file($directory.'/autoload.php'));

if (null === $vendor) {
    throw new RuntimeException('Run composer install first.');
}

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = is_string($uri) ? strtok($uri, '?') : false;
$scripts = ['/assets/stimulus.js' => __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js'];

foreach (['checkin_desk_controller.js', 'lib/csrf.js', 'lib/decide.js', 'lib/i18n.js', 'lib/offline.js', 'lib/store.js'] as $script) {
    $scripts['/assets/checkin/'.$script] = $package.'/assets/src/'.$script;
}

if (is_string($path) && isset($scripts[$path])) {
    header('Content-Type: text/javascript');
    readfile($scripts[$path]);

    return true;
}

require $vendor.'/autoload.php';

// In debug, so that the container follows the code the suite runs on.
$kernel = new TestKernel(TestKernel::BROWSER, true);
$kernel->boot();
$connection = $kernel->getContainer()->get('doctrine.dbal.default_connection');
assert($connection instanceof Connection);

if (!$connection->createSchemaManager()->tablesExist(['phil_checkin_scan'])) {
    foreach (new DoctrineCheckInLedger($connection)->configureSchema(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
        $connection->executeStatement($sql);
    }
}

if ('/' === $path) {
    $desk = $kernel->getContainer()->get('test.'.DeskConfig::class);
    assert($desk instanceof DeskConfig);
    $config = htmlspecialchars(json_encode($desk->for(CheckpointId::of('hall')), \JSON_THROW_ON_ERROR), \ENT_QUOTES);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Check-in</title>
            <script type="importmap">{"imports": {"@hotwired/stimulus": "/assets/stimulus.js"}}</script>
            <script type="module">
                import { Application } from '@hotwired/stimulus';
                import DeskController from '/assets/checkin/checkin_desk_controller.js';

                const application = Application.start();
                application.register('phil--checkin-bundle--checkin-desk', DeskController);
                window.application = application;
                document.documentElement.dataset.started = 'true';
            </script>
        </head>
        <body>
            <form data-controller="phil--checkin-bundle--checkin-desk"
                  data-phil--checkin-bundle--checkin-desk-config-value="{$config}"
                  data-action="phil--checkin-bundle--checkin-desk#submit">
                <label>Code <input data-phil--checkin-bundle--checkin-desk-target="code" autocomplete="off" autofocus></label>
                <output data-phil--checkin-bundle--checkin-desk-target="result" role="status"></output>
                <p data-phil--checkin-bundle--checkin-desk-target="state"></p>
            </form>
        </body>
        </html>
        HTML;

    return true;
}

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

return true;
