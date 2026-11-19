<?php

declare(strict_types=1);

/*
 * The page `composer bench:worker phil/reactions-bundle` measures: FrankenPHP keeps this script alive across requests,
 * so the kernel built here serves every request, as in an application. Each request is the page of the functional
 * suite for a member, three components of two contents with their reactions read in one batch from the SQLite file of
 * the kernel.
 */

use Phil\ReactionsBundle\Application\Reactions;
use Phil\ReactionsBundle\Tests\Functional\App\TestKernel;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\Request;

// The package's own vendor/ once split, the monorepo's while it is developed there.
$autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname(__DIR__, 4).'/vendor/autoload.php';

$kernel = new TestKernel('test', false, 'ux-kit', 'ui');
$kernel->boot();
$kernel->resetDatabase();

$reactions = $kernel->getContainer()->get(Reactions::class);
assert($reactions instanceof Reactions);

foreach (['linus', 'grace', 'alan'] as $member) {
    $reactions->react(ResourceReference::of('article', '1'), $member, 'like');
}

$page = static function () use ($kernel): void {
    $request = Request::create('/article', server: ['HTTP_X_MEMBER' => 'ada']);
    $response = $kernel->handle($request);
    $response->send();

    $kernel->terminate($request, $response);
};

while (frankenphp_handle_request($page)) {
    gc_collect_cycles();
}
