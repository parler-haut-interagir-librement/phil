<?php

declare(strict_types=1);

/*
 * A body nobody wrote, posted from a page of the site: the controller answers 204, 400 or 413, and never fails.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\VideoBundle\Application\NoViewingConsent;
use Phil\VideoBundle\Application\ViewingTracker;
use Phil\VideoBundle\Infrastructure\Http\ProgressController;
use Phil\VideoBundle\Infrastructure\Memory\InMemoryViewingStore;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$controller = new ProgressController(new ViewingTracker(new NoViewingConsent(), new InMemoryViewingStore(), new EventDispatcher()));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($controller): void {
    $request = Request::create('https://example.com/_phil/video/progress', 'POST', content: $input);
    $request->headers->set('Sec-Fetch-Site', 'same-origin');

    if (!in_array($controller($request)->getStatusCode(), [204, 400, 413], true)) {
        throw new LogicException('The controller answers 204, 400 or 413.');
    }
});
$config->setMaxLen(2048);
