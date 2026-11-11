<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional;

use Phil\DddBundle\PhilDddBundle;
use Phil\DddBundle\Tests\Functional\App\Membership\CountMembersHandler;
use Phil\DddBundle\Tests\Functional\App\Membership\RegisterMemberHandler;
use Phil\DddBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The check the documentation recommends: `debug:messenger` lists each handler under its bus.
 */
#[CoversClass(PhilDddBundle::class)]
final class DebugMessengerCommandTest extends FunctionalTestCase
{
    public function testEachHandlerIsListedUnderItsBus(): void
    {
        self::assertStringContainsString(RegisterMemberHandler::class, $this->handlersOf(TestKernel::COMMAND_BUS));
        self::assertStringNotContainsString(CountMembersHandler::class, $this->handlersOf(TestKernel::COMMAND_BUS));

        self::assertStringContainsString(CountMembersHandler::class, $this->handlersOf(TestKernel::QUERY_BUS));
        self::assertStringNotContainsString(RegisterMemberHandler::class, $this->handlersOf(TestKernel::QUERY_BUS));
    }

    private function handlersOf(string $bus): string
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $tester = new CommandTester(new Application($kernel)->find('debug:messenger'));
        self::assertSame(0, $tester->execute(['bus' => $bus]));

        return $tester->getDisplay();
    }
}
