<?php

declare(strict_types=1);

/*
 * The front of the application the browser suite drives: the kernel of the functional suite, its page wrapped in a
 * document that loads Stimulus, the Live Component controller and the bundle's controllers by an import map, and the
 * files that map names. The reactions are kept in the SQLite file of the kernel between two requests, each a process of
 * its own. The member is the cookie `member`, "ada" when there is none, and nobody when it is empty.
 */

use Phil\ReactionsBundle\Tests\Functional\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 3);
$monorepo = dirname($root, 2);
require (is_file($monorepo.'/vendor/autoload.php') ? $monorepo : $root).'/vendor/autoload.php';

$stimulus = getenv('PHIL_STIMULUS_JS');
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$vendor = is_file($monorepo.'/vendor/autoload.php') ? $monorepo.'/vendor' : $root.'/vendor';
$files = [
    '/assets/stimulus.js' => is_string($stimulus) ? $stimulus : '',
    '/assets/live.js' => $vendor.'/symfony/ux-live-component/assets/dist/live_controller.js',
    '/assets/reactions_controller.js' => $root.'/assets/src/reactions_controller.js',
    '/assets/picker_controller.js' => $root.'/assets/src/picker_controller.js',
    '/assets/lib/search.js' => $root.'/assets/src/lib/search.js',
    '/assets/reactions.css' => $root.'/assets/styles/reactions.css',
];
$path = (string) strtok(is_string($uri) ? $uri : '/', '?');

if (isset($files[$path])) {
    header('Content-Type: '.(str_ends_with($path, '.css') ? 'text/css' : 'text/javascript'));
    readfile($files[$path]);

    return;
}

$member = $_COOKIE['member'] ?? 'ada';
$_SERVER['HTTP_X_MEMBER'] = is_string($member) ? $member : 'ada';
$_SERVER['HTTP_SEC_FETCH_SITE'] ??= 'same-origin';

$kernel = new TestKernel('test', false, 'ux-kit', 'ui');
$kernel->boot();

$request = Request::createFromGlobals();
$response = $kernel->handle($request);

if ('/article' === $path && $response->isSuccessful()) {
    $response->setContent(<<<HTML
        <!doctype html>
        <html lang="fr">
        <head>
            <meta charset="utf-8">
            <title>Réactions</title>
            <link rel="stylesheet" href="/assets/reactions.css">
            <script type="importmap">{"imports": {
                "@hotwired/stimulus": "/assets/stimulus.js",
                "@symfony/ux-live-component": "/assets/live.js"
            }}</script>
        </head>
        <body>
        <main>{$response->getContent()}</main>
        <script type="module">
            import { Application } from '@hotwired/stimulus';
            import Live from '@symfony/ux-live-component';
            import Reactions from '/assets/reactions_controller.js';
            import Picker from '/assets/picker_controller.js';
            const application = Application.start();
            application.register('live', Live);
            application.register('phil--reactions-bundle--reactions', Reactions);
            application.register('phil--reactions-bundle--picker', Picker);
            document.documentElement.dataset.started = 'true';
        </script>
        </body>
        </html>
        HTML);
}

$response->send();
$kernel->terminate($request, $response);
