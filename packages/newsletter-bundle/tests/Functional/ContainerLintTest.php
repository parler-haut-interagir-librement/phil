<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Functional;

use Phil\NewsletterBundle\Infrastructure\DependencyInjection\NewsletterServices;
use Phil\NewsletterBundle\PhilNewsletterBundle;
use Phil\NewsletterBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The services of the bundle as the container compiles them: each argument, the inline ones included, matches the
 * type its constructor declares, so `lint:container` of an application that installs the bundle passes. The kernel
 * runs without debug, so that the command compiles a container of its own instead of reading the dump a debug kernel
 * keeps in its cache; that compilation starts the bundles anew, so the kernel is left as the command leaves it.
 */
#[CoversClass(PhilNewsletterBundle::class)]
#[CoversClass(NewsletterServices::class)]
final class ContainerLintTest extends TestCase
{
    public function testTheArgumentsOfEveryServiceMatchTheirTypes(): void
    {
        $application = new Application(new TestKernel('test', false));
        $tester = new CommandTester($application->find('lint:container'));

        $status = $tester->execute([]);

        self::assertSame(0, $status, $tester->getDisplay());
        self::assertStringContainsString('[OK] The container was linted successfully', $tester->getDisplay());
    }
}
