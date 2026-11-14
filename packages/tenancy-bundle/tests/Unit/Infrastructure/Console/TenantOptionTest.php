<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Console;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Infrastructure\Console\TenantOption;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\RecordingActivation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(TenantOption::class)]
final class TenantOptionTest extends TestCase
{
    public function testTheCommandRunsInTheTenantOfItsOptionUntilItEnds(): void
    {
        $activation = new RecordingActivation();
        $context = new TenantContext([$activation]);
        $option = new TenantOption($context);
        $command = $this->command();

        $option->onCommand($this->event($command, ['--tenant' => 'retail']));

        self::assertSame('retail', $context->current()?->value);
        self::assertSame(0, $command->run(new ArrayInput(['--tenant' => 'retail']), new NullOutput()));

        $definition = $command->getNativeDefinition()->getOption('tenant');
        self::assertTrue($definition->isValueRequired());
        self::assertNull($definition->getShortcut());
        self::assertSame('The tenant the command acts in', $definition->getDescription());
        self::assertSame('tenant', TenantOption::NAME);

        $option->onTerminate();
        self::assertNull($context->current());
        self::assertSame(['retail', '-'], $activation->calls);
    }

    public function testWithoutTheOptionTheCommandRunsInNoTenant(): void
    {
        $activation = new RecordingActivation();
        $option = new TenantOption(new TenantContext([$activation]));

        $option->onCommand($this->event($this->command(), []));
        $option->onTerminate();

        self::assertSame([], $activation->calls);
    }

    public function testACommandWithItsOwnTenantOptionKeepsIt(): void
    {
        $context = new TenantContext();
        $command = $this->command();
        $command->addOption('tenant', 't', InputOption::VALUE_REQUIRED);

        new TenantOption($context)->onCommand($this->event($command, ['--tenant' => 'retail']));

        self::assertNull($context->current());
        self::assertSame('t', $command->getDefinition()->getOption('tenant')->getShortcut());
    }

    public function testAnEventWithoutACommandIsLeftAlone(): void
    {
        $context = new TenantContext();

        new TenantOption($context)->onCommand(new ConsoleCommandEvent(null, new ArrayInput([]), new NullOutput()));

        self::assertNull($context->current());
    }

    public function testAMalformedTenantStopsTheCommand(): void
    {
        $this->expectException(InvalidValue::class);

        new TenantOption(new TenantContext())->onCommand($this->event($this->command(), ['--tenant' => '']));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function event(Command $command, array $parameters): ConsoleCommandEvent
    {
        $input = new ArrayInput($parameters);

        // As the application does: a first binding, whose errors wait for the listeners.
        try {
            $command->mergeApplicationDefinition();
            $input->bind($command->getDefinition());
        } catch (ExceptionInterface) {
        }

        return new ConsoleCommandEvent($command, $input, new NullOutput());
    }

    private function command(): Command
    {
        $command = new class('app:report') extends Command {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        };
        $command->setApplication(new Application());

        return $command;
    }
}
