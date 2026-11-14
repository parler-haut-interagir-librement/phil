<?php

declare(strict_types=1);

/*
 * The console of the test application, which the migration command runs once per database.
 */

use Phil\TenancyDatabaseBundle\Tests\Integration\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

$root = dirname(__DIR__, 5);
require (is_file($root.'/vendor/autoload.php') ? $root : dirname(__DIR__, 3)).'/vendor/autoload.php';

exit(new Application(new TestKernel('console', false))->run());
