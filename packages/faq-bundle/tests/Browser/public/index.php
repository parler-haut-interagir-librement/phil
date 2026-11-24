<?php

declare(strict_types=1);

/*
 * The front of the application the browser suite drives: the kernel of the functional suite, its page of questions
 * wrapped in a document that loads Stimulus and the bundle's controller by an import map, and the files that map names.
 * The questions are kept in the SQLite file of the kernel between two requests, each a process of its own.
 */

use Phil\FaqBundle\Tests\Functional\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 3);
$monorepo = dirname($root, 2);
require (is_file($monorepo.'/vendor/autoload.php') ? $monorepo : $root).'/vendor/autoload.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$files = [
    '/assets/stimulus.js' => __DIR__.'/../node_modules/@hotwired/stimulus/dist/stimulus.js',
    '/assets/faq_controller.js' => $root.'/assets/src/faq_controller.js',
    '/assets/lib/search.js' => $root.'/assets/src/lib/search.js',
    '/assets/lib/voted.js' => $root.'/assets/src/lib/voted.js',
];
$path = (string) strtok(is_string($uri) ? $uri : '/', '?');

if (isset($files[$path])) {
    header('Content-Type: text/javascript');
    readfile($files[$path]);

    return;
}

$kernel = new TestKernel('test', false);
$kernel->boot();

$request = Request::createFromGlobals();
$response = $kernel->handle($request);

if ('/faq/' === $path && $response->isSuccessful()) {
    $content = (string) $response->getContent();
    $script = <<<'HTML'
        <script type="importmap">{"imports": {"@hotwired/stimulus": "/assets/stimulus.js"}}</script>
        <script type="module">
            import { Application } from '@hotwired/stimulus';
            import Faq from '/assets/faq_controller.js';
            const application = Application.start();
            application.register('phil--faq-bundle--faq', Faq);
            document.documentElement.dataset.started = 'true';
        </script>
        HTML;
    $response->setContent(str_replace('</body>', $script.'</body>', $content));
}

$response->send();
$kernel->terminate($request, $response);
