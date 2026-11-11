<?php

declare(strict_types=1);

namespace Phil\MigrationBundle\Tests\Functional;

use Override;
use Phil\MigrationBundle\PhilMigrationBundle;
use Phil\MigrationBundle\Tests\Functional\App\RecordingPublisher;
use Phil\MigrationBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(PhilMigrationBundle::class)]
final class MigrationBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testADryRunAnnouncesNothing(): void
    {
        self::assertSame([], $this->migrate(['--dry-run' => true]));
    }

    public function testOneRunIsAnnouncedOnce(): void
    {
        self::assertSame([
            ['discord_alice', "# Nouvelle version\n\n* Nouveau calendrier.\n* Mise à jour des librairies."],
            ['discord_news', "# Le calendrier\nTous les événements.\nPour voir la nouvelle https://example.com/calendrier"],
        ], $this->migrate());
    }

    /**
     * What the publisher was given while the migrations ran.
     *
     * @param array<string, bool> $options
     *
     * @return list<array{string, string}>
     */
    private function migrate(array $options = []): array
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        $output = new BufferedOutput();

        self::assertSame(0, $application->run(new ArrayInput(['command' => 'doctrine:migrations:migrate', '--no-interaction' => true, ...$options]), $output), $output->fetch());
        $publisher = $this->service(RecordingPublisher::class);
        self::assertInstanceOf(RecordingPublisher::class, $publisher);

        return $publisher->published;
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
