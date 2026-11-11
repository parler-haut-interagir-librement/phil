<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Phil\ConsoleBundle\Infrastructure\Monolog\ChannelDebugSwitch;
use Phil\ConsoleBundle\Infrastructure\Monolog\OutputHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(ChannelDebugSwitch::class)]
#[CoversClass(OutputHandler::class)]
final class MonologTest extends TestCase
{
    public function testTheNamedChannelsWriteTheirDebugLogs(): void
    {
        $existing = new TestHandler();
        $app = new Logger('app', [$existing]);
        $doctrine = new Logger('doctrine');
        $switch = new ChannelDebugSwitch(new ServiceLocator([
            'app' => static fn (): Logger => $app,
            'doctrine' => static fn (): Logger => $doctrine,
            'other' => static fn (): NullLogger => new NullLogger(),
        ]), ['app', 'doctrine', 'other']);
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);

        self::assertSame(['app', 'doctrine', 'other'], $switch->channels());
        $switch->enable(['app', 'other', 'unknown'], $output);
        $app->debug('A <error>debug</error> message', ['id' => 3]);
        $doctrine->debug('not enabled');

        self::assertMatchesRegularExpression('/^\[\S+\] app\.DEBUG: A <error>debug<\/error> message \{"id":3\} \[\]\n$/', $output->fetch());
        self::assertCount(2, $app->getHandlers());

        $switch->disable();
        $app->debug('after');
        self::assertSame('', $output->fetch());
        self::assertSame([$existing], $app->getHandlers());
        self::assertCount(2, $existing->getRecords());

        $switch->enable(['doctrine'], $output);
        $switch->reset();

        $doctrine->info('after the reset');
        self::assertSame('', $output->fetch());
        $switch->disable();
    }

    public function testTheHandlerWritesTheMessageWithoutFormatter(): void
    {
        $output = new BufferedOutput();
        $handler = new class($output) extends AbstractProcessingHandler {
            public function __construct(private readonly OutputInterface $output)
            {
                parent::__construct();
            }

            protected function write(LogRecord $record): void
            {
                $inner = new OutputHandler($this->output);
                $inner->setFormatter(new class implements FormatterInterface {
                    public function format(LogRecord $record): mixed
                    {
                        return ['not a string'];
                    }

                    public function formatBatch(array $records): mixed
                    {
                        return [];
                    }
                });
                $inner->handle($record);
            }
        };
        new Logger('app', [$handler])->debug('<b>raw</b>');

        self::assertSame("<b>raw</b>\n", $output->fetch());
        self::assertTrue(new OutputHandler($output)->isHandling(new LogRecord(new DateTimeImmutable(), 'app', Level::Debug, 'm')));
    }
}
